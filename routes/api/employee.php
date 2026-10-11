<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Employee\AnnouncementController;
use App\Http\Controllers\Api\Employee\DashboardController;
use App\Http\Controllers\Api\Employee\LeaveRequestController;
use App\Http\Controllers\Api\Employee\MyDocumentController;
use App\Http\Controllers\Api\Employee\PresenceController;
use App\Http\Controllers\Api\Employee\ProfileController;
use App\Http\Controllers\Api\Employee\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes "self-service" (auth:sanctum uniquement)
|--------------------------------------------------------------------------
| Accessibles à TOUT utilisateur connecté quel que soit son rôle :
| son propre profil, ses propres tâches/présences/congés, la
| consultation (lecture) des annonces qui le concernent.
*/

// Session / profil
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/user', [AuthController::class, 'user']);
Route::post('/change-password', [AuthController::class, 'updatePassword']);

Route::get('/me', [ProfileController::class, 'show']);
Route::get('/check-manager-status', [ProfileController::class, 'managerStatus']);
Route::post('/me/profile-photo', [ProfileController::class, 'uploadPhoto']);
Route::delete('/me/profile-photo', [ProfileController::class, 'deletePhoto']);

// Mon dossier : l'employé dépose et consulte ses propres pièces
Route::get('/me/documents', [MyDocumentController::class, 'index']);
Route::post('/me/documents', [MyDocumentController::class, 'store']);
Route::get('/me/documents/{document}/download', [MyDocumentController::class, 'download']);
Route::delete('/me/documents/{document}', [MyDocumentController::class, 'destroy']);

// Dashboard personnel
Route::get('/me/dashboard-summary', [DashboardController::class, 'summary']);

// Mes tâches
Route::get('/me/tasks', [TaskController::class, 'myTasks']);
Route::get('/tasks/{task}', [TaskController::class, 'show']);
Route::post('/tasks/{task}/submit-report', [TaskController::class, 'submitReport']);
Route::post('/tasks/{task}/mark-completed', [TaskController::class, 'markAsCompleted']);
Route::get('/tasks/{task}/download-file', [TaskController::class, 'downloadTaskFile']);
Route::get('/tasks/{task}/download-report', [TaskController::class, 'downloadReportFile']);

// Mes présences
Route::get('/me/presences', [PresenceController::class, 'myPresences']);
Route::get('/me/presences/stats', [PresenceController::class, 'stats']);
Route::post('/presences/check-in', [PresenceController::class, 'store']);
Route::put('/presences/{presence}/check-out', [PresenceController::class, 'update']);

// Mes congés
Route::get('/me/leave-requests', [LeaveRequestController::class, 'myLeaveRequests']);
Route::get('/me/leave-requests/{id}', [LeaveRequestController::class, 'showMine']);
Route::put('/me/leave-requests/{id}', [LeaveRequestController::class, 'updateMine']);
Route::post('/leave-requests', [LeaveRequestController::class, 'store']);

// Annonces — consultation (tout le monde), gestion réservée à manager/admin (routes/api/manager.php)
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);
Route::get('/me/announcements', [AnnouncementController::class, 'myAnnouncements']);
