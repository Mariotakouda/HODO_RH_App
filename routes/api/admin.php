<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\EmployeeFileController;
use App\Http\Controllers\Api\Admin\EmployeeRoleController;
use App\Http\Controllers\Api\Admin\LeaveRequestController;
use App\Http\Controllers\Api\Admin\ManagerController;
use App\Http\Controllers\Api\Admin\PresenceController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes admin strict (middleware 'admin')
|--------------------------------------------------------------------------
| BUGFIX : avant, ce groupe utilisait la même classe que 'is_manager' et
| était donc accessible aux managers aussi. Ici, c'est un vrai admin-only.
| Si un écran manager dépend d'une de ces routes, dis-le-moi et on la
| déplace dans routes/api/manager.php.
*/

Route::get('/dashboard-summary', [DashboardController::class, 'index']);
Route::get('/departments/list', [DepartmentController::class, 'list']);

Route::apiResource('employees', EmployeeController::class)->only(['store', 'update', 'destroy']);

// Affectation, archivage et invitation
Route::post('/employees/{employee}/transfer', [EmployeeController::class, 'transfer']);
Route::post('/employees/{employee}/archive', [EmployeeController::class, 'archive']);
Route::post('/employees/{employee}/restore', [EmployeeController::class, 'restore']);
Route::post('/employees/{employee}/resend-invitation', [EmployeeController::class, 'resendInvitation']);
// NB : pas /employees/archived, déjà capturé par GET /employees/{employee} (routes manager).
Route::get('/archives/employees', [EmployeeController::class, 'archived']);

// Dossier employé (pièces stockées sur un disque privé)
Route::get('/employees/{employee}/file', [EmployeeFileController::class, 'show']);
Route::post('/employees/{employee}/documents', [EmployeeFileController::class, 'storeDocument']);
Route::get('/employees/{employee}/documents/{document}/download', [EmployeeFileController::class, 'downloadDocument']);
Route::delete('/employees/{employee}/documents/{document}', [EmployeeFileController::class, 'destroyDocument']);
Route::apiResource('departments', DepartmentController::class);
Route::apiResource('roles', RoleController::class);
Route::apiResource('managers', ManagerController::class);

Route::get('/employee-roles', [EmployeeRoleController::class, 'index']);
Route::post('/employee-roles', [EmployeeRoleController::class, 'store']);
Route::delete('/employee-roles/{employee_id}/{role_id}', [EmployeeRoleController::class, 'destroy']);

Route::get('/presences/export', [PresenceController::class, 'export']);
Route::get('/presences/export-data', [PresenceController::class, 'exportData']);
Route::delete('/presences/{presence}', [PresenceController::class, 'destroy']);

Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
Route::delete('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy']);
