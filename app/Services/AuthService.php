<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authentifie l'utilisateur et retourne [User, token].
     *
     * @throws ValidationException si les identifiants sont incorrects
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects'],
            ]);
        }

        $employee = null;
        if (in_array(strtolower($user->role), ['employee', 'manager'])) {
            $employee = $user->employee()->with(['department', 'roles'])->first();
        }

        // Employé archivé (démission, renvoi, retraite) : plus d'accès à l'application.
        if ($employee && $employee->isArchived()) {
            throw new AuthorizationException("Ce compte a été désactivé. Contactez l'administration.");
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return [$user, $token, $employee];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * @throws ValidationException si le mot de passe actuel est incorrect
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ["L'ancien mot de passe est incorrect."],
            ]);
        }

        $user->password = Hash::make($newPassword);
        $user->needs_password_change = false;
        $user->save();
    }

    public function sendResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(array $credentials): string
    {
        $callback = function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'needs_password_change' => false,
            ])->setRememberToken(Str::random(60));
            $user->save();
        };

        // « Mot de passe oublié » (valable 60 min) puis lien d'invitation (valable 72 h).
        $status = Password::broker('users')->reset($credentials, $callback);

        if ($status === Password::INVALID_TOKEN) {
            $status = Password::broker('invitations')->reset($credentials, $callback);
        }

        return $status;
    }
}
