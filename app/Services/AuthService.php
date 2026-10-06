<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Owns session authentication: creating the account, verifying credentials and tearing the session down.
 */
class AuthService
{
    /** @param array<string, mixed> $attributes */
    public function register(array $attributes, Request $request): User
    {
        $user = User::create($attributes);
        $this->establishSession($user, $request);

        return $user;
    }

    public function attempt(string $email, string $password, Request $request): ?User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        $this->establishSession($user, $request);

        return $user;
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();
    }

    private function establishSession(User $user, Request $request): void
    {
        Auth::login($user);
        $request->session()->regenerate();
    }
}
