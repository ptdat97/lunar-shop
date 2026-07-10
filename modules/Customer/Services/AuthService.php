<?php

namespace Modules\Customer\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Account creation + credential verification shared by the cookie (SPA) and
 * token (PAT) auth controllers — one source of registration logic (standards
 * §4) instead of each controller creating users itself.
 */
class AuthService
{
    /**
     * Create an account and fire Registered (verification/welcome listeners).
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        return $user;
    }

    /**
     * Verify email + password, throwing the standard `auth.failed` validation
     * error on mismatch — the same shape the session login flow returns.
     *
     * @throws ValidationException
     */
    public function verifyCredentials(string $email, string $password): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $user;
    }

    /**
     * Change a password after proving the current one.
     *
     * @throws ValidationException when the current password does not match
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->update(['password' => Hash::make($newPassword)]);
    }
}
