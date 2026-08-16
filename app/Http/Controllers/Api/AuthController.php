<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Single-user auth.
 *
 * There is no registration endpoint on purpose — the one account is created
 * with `php artisan lumen:user`. An open journal API with a signup route would
 * be a standing invitation.
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
                'email' => 'Priveľa pokusov. Skús to o '.RateLimiter::availableIn($throttleKey).' s.',
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, decaySeconds: 300);

            throw ValidationException::withMessages([
                'email' => 'Nesprávny e-mail alebo heslo.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // One token per device, so losing a phone means revoking just that one.
        $device = $validated['device'] ?? 'mobil';
        $user->tokens()->where('name', $device)->delete();

        return response()->json([
            'token' => $user->createToken($device)->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }
}
