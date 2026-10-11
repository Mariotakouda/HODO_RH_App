<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsManagerOrAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — point d'entrée unique
|--------------------------------------------------------------------------
| Ce fichier ne fait QUE grouper par niveau d'accès et inclure les
| fichiers dédiés. Le détail de chaque route vit dans routes/api/*.php,
| rangé exactement comme les contrôleurs (Auth / Employee / Manager / Admin).
|
|   routes/api/auth.php      -> public
|   routes/api/employee.php  -> auth:sanctum (tout utilisateur connecté)
|   routes/api/manager.php   -> auth:sanctum + 'manager' (manager OU admin)
|   routes/api/admin.php     -> auth:sanctum + 'admin'   (admin uniquement)
*/

require __DIR__ . '/api/auth.php';

Route::middleware('auth:sanctum')->group(function () {
    require __DIR__ . '/api/employee.php';

    Route::middleware(EnsureUserIsManagerOrAdmin::class)->group(function () {
        require __DIR__ . '/api/manager.php';
    });

    Route::middleware(EnsureUserIsAdmin::class)->group(function () {
        require __DIR__ . '/api/admin.php';
    });
});
