<?php

// use Illuminate\Foundation\Application;
// use Illuminate\Foundation\Configuration\Exceptions;
// use Illuminate\Foundation\Configuration\Middleware;

// return Application::configure(basePath: dirname(__DIR__))
//     ->withRouting(
//         web: __DIR__.'/../routes/web.php',
//         api: __DIR__.'/../routes/api.php',
//         commands: __DIR__.'/../routes/console.php',
//         health: '/up',
//     )
//     ->withMiddleware(function (Middleware $middleware): void {
//         //
//     })
//     ->withExceptions(function (Exceptions $exceptions): void {
//         //
//     })->create();

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use App\Exceptions\Handler;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function ($middleware) {
        // BUGFIX : avant, 'admin' et 'is_manager' pointaient tous les deux vers
        // la même classe (qui ne testait que isManager()) : un manager pouvait
        // donc accéder aux routes soi-disant réservées aux admins. Chaque alias
        // a maintenant sa propre classe avec la bonne vérification.
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'manager' => \App\Http\Middleware\EnsureUserIsManagerOrAdmin::class,
        ]);

        // Par défaut, Laravel n'applique ConvertEmptyStringsToNull/TrimStrings
        // qu'au groupe 'web', pas 'api'. Résultat : un champ optionnel envoyé
        // comme chaîne vide "" (ex: due_date="" dans un FormData) n'est jamais
        // converti en null, et échoue la règle de validation "nullable|date"
        // (une chaîne vide n'est pas "null" pour Laravel). On les ajoute donc
        // aussi à l'API pour que les champs optionnels laissés vides passent.
        $middleware->api(append: [
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Ici tu peux configurer ton handler
        $exceptions->report(function (\Throwable $e) {
            // logique de reporting personnalisée
        });
    })
    ->create();

