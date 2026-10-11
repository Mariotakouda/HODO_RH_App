<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorise uniquement les administrateurs.
 * Alias de route : 'admin'
 *
 * BUGFIX : dans l'ancienne version, 'admin' et 'is_manager' pointaient
 * vers la même classe (AdminMiddleware), qui testait isManager() —
 * donc un manager pouvait accéder aux routes soi-disant "admin only"
 * (CRUD départements, rôles, managers, dashboard global...).
 * Ici, 'admin' teste réellement isAdmin().
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Accès refusé — réservé aux administrateurs.',
        ], 403);
    }
}
