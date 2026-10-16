<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * Sign in with Apple, server side.
 *
 * The app gets an identity token (a JWT signed by Apple) from the system sheet
 * and hands it over; here it is verified against Apple's published keys, so a
 * forged token can never mint a session. No third-party auth service involved.
 */
class AppleSignIn
{
    private const ISSUER = 'https://appleid.apple.com';

    private const KEYS_URL = 'https://appleid.apple.com/auth/keys';

    /**
     * Verified claims of an identity token: `sub` (stable Apple user id),
     * and `email` / `email_verified` when the user shared one.
     *
     * @return array<string, mixed>
     *
     * @throws UnexpectedValueException when the token is not genuine, expired
     *                                  or issued for another app
     */
    public function verify(string $identityToken): array
    {
        try {
            $claims = (array) JWT::decode($identityToken, $this->keys());
        } catch (UnexpectedValueException $e) {
            // Apple rotates keys; a token signed with a new one fails against
            // the cached set — refetch once before calling it invalid.
            Cache::forget('apple.jwks');
            $claims = (array) JWT::decode($identityToken, $this->keys());
        }

        if (($claims['iss'] ?? null) !== self::ISSUER) {
            throw new UnexpectedValueException('Wrong issuer.');
        }

        if (($claims['aud'] ?? null) !== config('services.apple.client_id')) {
            throw new UnexpectedValueException('Token issued for another app.');
        }

        if (empty($claims['sub'])) {
            throw new UnexpectedValueException('Missing subject.');
        }

        return $claims;
    }

    /** True when the .p8 key is configured, so codes can be exchanged and accounts revoked. */
    public function canRevoke(): bool
    {
        return filled(config('services.apple.team_id'))
            && filled(config('services.apple.key_id'))
            && filled(config('services.apple.private_key'));
    }

    /**
     * Trades the one-time authorization code for a refresh token — the only
     * thing Apple's revoke endpoint accepts later. Best effort: sign-in must
     * not fail because of it.
     */
    public function refreshToken(string $authorizationCode): ?string
    {
        if (! $this->canRevoke()) {
            return null;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::ISSUER.'/auth/token', [
                'client_id' => config('services.apple.client_id'),
                'client_secret' => $this->clientSecret(),
                'code' => $authorizationCode,
                'grant_type' => 'authorization_code',
            ]);

            return $response->successful() ? $response->json('refresh_token') : null;
        } catch (Throwable $e) {
            Log::warning('Apple code exchange failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Tells Apple the user deleted their account, so the app's link to it disappears too. */
    public function revoke(string $refreshToken): bool
    {
        if (! $this->canRevoke()) {
            return false;
        }

        try {
            return Http::asForm()->timeout(10)->post(self::ISSUER.'/auth/revoke', [
                'client_id' => config('services.apple.client_id'),
                'client_secret' => $this->clientSecret(),
                'token' => $refreshToken,
                'token_type_hint' => 'refresh_token',
            ])->successful();
        } catch (Throwable $e) {
            Log::warning('Apple revoke failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /** @return array<string, Key> */
    private function keys(): array
    {
        $jwks = Cache::remember('apple.jwks', now()->addDay(), function () {
            $response = Http::timeout(10)->get(self::KEYS_URL);

            if (! $response->successful()) {
                throw new RuntimeException('Apple keys unavailable.');
            }

            return $response->json();
        });

        return JWK::parseKeySet($jwks);
    }

    /** Short-lived ES256 JWT that authenticates this server to Apple. */
    private function clientSecret(): string
    {
        $now = time();

        return JWT::encode([
            'iss' => config('services.apple.team_id'),
            'iat' => $now,
            'exp' => $now + 300,
            'aud' => self::ISSUER,
            'sub' => config('services.apple.client_id'),
        ], str_replace('\n', "\n", (string) config('services.apple.private_key')), 'ES256', config('services.apple.key_id'));
    }
}
