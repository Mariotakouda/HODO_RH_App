<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->validated();

            [$user, $token, $employee] = $this->authService->login(
                $credentials['email'],
                $credentials['password'],
            );

            return response()->json([
                'message' => 'Connexion réussie',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'needs_password_change' => $user->needs_password_change,
                ],
                'employee' => $employee,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Identifiants incorrects'], 401);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            Log::error('Erreur login : ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $this->authService->logout($request->user());
            return response()->json(['message' => 'Déconnexion réussie']);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la déconnexion'], 500);
        }
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function updatePassword(ChangePasswordRequest $request)
    {
        try {
            $validated = $request->validated();

            $this->authService->changePassword(
                $request->user(),
                $validated['current_password'],
                $validated['new_password'],
            );

            return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function sendResetLinkEmail(ForgotPasswordRequest $request)
    {
        $status = $this->authService->sendResetLink($request->validated()['email']);

        if ($status === PasswordBroker::RESET_LINK_SENT) {
            return response()->json(['message' => 'Lien de réinitialisation envoyé par email !']);
        }

        return response()->json(['message' => "Impossible d'envoyer le lien (email introuvable ou erreur serveur)."], 400);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = $this->authService->resetPassword(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        if ($status === PasswordBroker::PASSWORD_RESET) {
            return response()->json(['message' => 'Votre mot de passe a été réinitialisé avec succès.']);
        }

        return response()->json(['message' => 'Le lien est invalide ou a expiré.'], 400);
    }
}
