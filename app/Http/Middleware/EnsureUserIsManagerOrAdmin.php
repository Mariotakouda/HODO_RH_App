<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorise les administrateurs ET les managers (gestion d'équipe).
 * Alias de route : 'manager'
 *
 * isManager() couvre déjà admin + manager (rôle direct, table pivot,
 * table managers) et met le résultat en cache pour toute la durée
 * de la requête HTTP.
 */
class EnsureUserIsManagerOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if ($user->isManager()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Accès refusé — réservé aux administrateurs et managers.',
        ], 403);
    }
}
