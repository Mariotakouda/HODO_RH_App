<?php

use App\Http\Controllers\Api\Manager\AnnouncementController;
use App\Http\Controllers\Api\Manager\DashboardController;
use App\Http\Controllers\Api\Manager\EmployeeController;
use App\Http\Controllers\Api\Manager\LeaveRequestController;
use App\Http\Controllers\Api\Manager\PresenceController;
use App\Http\Controllers\Api\Manager\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes de gestion d'équipe (middleware 'manager' = admin OU manager)
|--------------------------------------------------------------------------
| L'admin voit tout, le manager est scoping à son propre département
| directement dans les services (voir App\Services\*Service).
*/

// Tableau de bord d'équipe (vue d'ensemble du département du manager)
Route::get('/manager/dashboard-summary', [DashboardController::class, 'summary']);

// Employés de l'équipe (lecture) — admin: tous, manager: son département
Route::get('/employees', [EmployeeController::class, 'index']);
Route::get('/employees/{employee}', [EmployeeController::class, 'show']);

// Tâches
Route::get('/tasks', [TaskController::class, 'index']);
Route::post('/tasks', [TaskController::class, 'store']);
Route::put('/tasks/{task}', [TaskController::class, 'update']);
Route::get('/manager/team-tasks', [TaskController::class, 'teamTasks']);

// Annonces (création/édition/suppression)
Route::post('/announcements', [AnnouncementController::class, 'store']);
Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);
Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

// Présences (vue globale de l'équipe)
Route::get('/presences', [PresenceController::class, 'index']);
Route::get('/presences/logs', [PresenceController::class, 'logs']);

// Congés (traitement des demandes)
Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
Route::put('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
Route::put('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
