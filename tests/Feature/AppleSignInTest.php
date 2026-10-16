<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sign in with Apple against a fake Apple: a throwaway RSA key stands in for
 * Apple's, published through a faked JWKS endpoint.
 */
class AppleSignInTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $this->privateKey);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response(['keys' => [[
                'kty' => 'RSA', 'kid' => 'test', 'use' => 'sig', 'alg' => 'RS256',
                'n' => $b64($rsa['n']), 'e' => $b64($rsa['e']),
            ]]]),
        ]);
    }

    private function token(array $claims = []): string
    {
        return JWT::encode(array_merge([
            'iss' => 'https://appleid.apple.com',
            'aud' => config('services.apple.client_id'),
            'sub' => '001234.apple.user',
            'iat' => time(),
            'exp' => time() + 600,
            'email' => 'alex@example.com',
            'email_verified' => 'true',
        ], $claims), $this->privateKey, 'RS256', 'test');
    }

    public function test_first_sign_in_creates_the_account(): void
    {
        $response = $this->postJson('/api/auth/apple', ['identityToken' => $this->token(), 'name' => 'Alex Doe'])
            ->assertOk()
            ->assertJsonPath('user.email', 'alex@example.com')
            ->assertJsonPath('user.name', 'Alex Doe')
            ->assertJsonPath('user.apple', true);

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('users', ['apple_id' => '001234.apple.user', 'password' => null]);
    }

    public function test_signing_in_again_reuses_the_account(): void
    {
        $first = $this->postJson('/api/auth/apple', ['identityToken' => $this->token()])->json('user.id');
        // Later tokens may carry no e-mail at all.
        $second = $this->postJson('/api/auth/apple', ['identityToken' => $this->token(['email' => null])])->json('user.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, User::count());
    }

    public function test_links_to_password_account_with_the_same_verified_email(): void
    {
        $existing = User::factory()->create(['email' => 'alex@example.com']);

        $this->postJson('/api/auth/apple', ['identityToken' => $this->token()])
            ->assertOk()
            ->assertJsonPath('user.id', $existing->id);

        $this->assertSame('001234.apple.user', $existing->fresh()->apple_id);
    }

    public function test_does_not_link_on_unverified_email(): void
    {
        $existing = User::factory()->create(['email' => 'alex@example.com']);

        $id = $this->postJson('/api/auth/apple', ['identityToken' => $this->token(['email_verified' => 'false'])])
            ->assertOk()
            ->json('user.id');

        $this->assertNotSame($existing->id, $id);
        $this->assertNull(User::find($id)->email);
    }

    public function test_rejects_token_for_another_app(): void
    {
        $this->postJson('/api/auth/apple', ['identityToken' => $this->token(['aud' => 'com.someone.else'])])
            ->assertUnprocessable();

        $this->assertSame(0, User::count());
    }

    public function test_rejects_forged_token(): void
    {
        $forged = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($forged, $pem);

        $this->postJson('/api/auth/apple', [
            'identityToken' => JWT::encode(['iss' => 'https://appleid.apple.com', 'aud' => config('services.apple.client_id'), 'sub' => 'x', 'exp' => time() + 60], $pem, 'RS256', 'test'),
        ])->assertUnprocessable();
    }

    public function test_apple_only_account_cannot_password_login(): void
    {
        $this->postJson('/api/auth/apple', ['identityToken' => $this->token()])->assertOk();

        $this->postJson('/api/login', ['email' => 'alex@example.com', 'password' => ''])
            ->assertUnprocessable();
        $this->postJson('/api/login', ['email' => 'alex@example.com', 'password' => 'anything'])
            ->assertUnprocessable();
    }

    public function test_login_errors_follow_accept_language(): void
    {
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'x'], ['Accept-Language' => 'sk-SK'])
            ->assertJsonPath('errors.email.0', 'Nesprávny e-mail alebo heslo.');

        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'x'], ['Accept-Language' => 'en-US'])
            ->assertJsonPath('errors.email.0', 'Incorrect email or password.');
    }

    public function test_deleting_the_account_removes_everything(): void
    {
        Storage::fake('r2');
        Sanctum::actingAs($user = User::factory()->create());
        $other = User::factory()->create();

        Entry::forceCreate(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'entry_date' => now(), 'text' => 'mine']);
        Storage::disk('r2')->put("media/{$user->id}/a.jpg", 'x');
        Storage::disk('r2')->put("media/{$other->id}/b.jpg", 'x');

        $this->deleteJson('/api/user')->assertNoContent();

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, Entry::where('user_id', $user->id)->count());
        Storage::disk('r2')->assertMissing("media/{$user->id}/a.jpg");
        Storage::disk('r2')->assertExists("media/{$other->id}/b.jpg");
    }
}
