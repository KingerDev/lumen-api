<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AppleSignIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnexpectedValueException;

/**
 * Auth.
 *
 * Two ways in: e-mail + password for accounts created with `php artisan
 * lumen:user` (there is still no password signup route), and Sign in with
 * Apple, which creates the account on first use. Either way the app gets the
 * same Sanctum token.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'string', 'max:100'],
        ]);

        // Throttled by IP+email so a stolen API URL cannot be brute-forced.
        $throttleKey = mb_strtolower($validated['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, maxAttempts: 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        // An Apple-only account has no password and can never match one.
        if (! $user?->password || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, decaySeconds: 300);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        return $this->session($user, $validated['device'] ?? null);
    }

    public function apple(Request $request, AppleSignIn $apple): JsonResponse
    {
        $validated = $request->validate([
            'identityToken' => ['required', 'string'],
            'authorizationCode' => ['nullable', 'string'],
            // Apple sends the name only on the very first sign-in, to the app.
            'name' => ['nullable', 'string', 'max:255'],
            'device' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $claims = $apple->verify($validated['identityToken']);
        } catch (UnexpectedValueException) {
            throw ValidationException::withMessages(['identityToken' => __('auth.apple_invalid')]);
        } catch (Throwable $e) {
            Log::warning('Apple sign-in unavailable', ['error' => $e->getMessage()]);
            abort(503, __('auth.apple_unavailable'));
        }

        $appleId = (string) $claims['sub'];
        $email = $claims['email'] ?? null;
        $emailVerified = in_array($claims['email_verified'] ?? false, [true, 'true'], true);

        $user = User::where('apple_id', $appleId)->first();

        // Same verified address as an existing password account → the same
        // person; link instead of splitting their journal across two accounts.
        if (! $user && $email && $emailVerified) {
            $user = User::where('email', $email)->whereNull('apple_id')->first();
            $user?->forceFill(['apple_id' => $appleId])->save();
        }

        $user ??= User::create([
            'apple_id' => $appleId,
            'email' => $email && ! User::where('email', $email)->exists() ? $email : null,
            'name' => trim((string) ($validated['name'] ?? '')) ?: ($email ? strstr($email, '@', true) : 'Lumen'),
        ]);

        if (! empty($validated['authorizationCode'])) {
            $refresh = $apple->refreshToken($validated['authorizationCode']);

            if ($refresh) {
                $user->forceFill(['apple_refresh_token' => $refresh])->save();
            }
        }

        return $this->session($user, $validated['device'] ?? null);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->profile($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    /**
     * Deletes the account and everything in it — entries, templates, media
     * rows by cascade, the photos in R2 explicitly — and revokes Sign in with
     * Apple. Required by App Review 5.1.1(v); not reversible.
     */
    public function destroy(Request $request, AppleSignIn $apple): JsonResponse
    {
        $user = $request->user();

        if ($user->apple_refresh_token) {
            $apple->revoke($user->apple_refresh_token);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        // After the rows are gone: a failed bucket call must not leave a
        // half-deleted account the user can still sign in to.
        try {
            Storage::disk('r2')->deleteDirectory(sprintf('media/%d', $user->id));
        } catch (Throwable $e) {
            Log::error('R2 cleanup after account deletion failed', ['user' => $user->id, 'error' => $e->getMessage()]);
        }

        return response()->json(status: 204);
    }

    /** One token per device, so losing a phone means revoking just that one. */
    private function session(User $user, ?string $device): JsonResponse
    {
        $device = $device ?: 'mobil';
        $user->tokens()->where('name', $device)->delete();

        return response()->json([
            'token' => $user->createToken($device)->plainTextToken,
            'user' => $this->profile($user),
        ]);
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'apple' => $user->apple_id !== null,
        ];
    }
}
