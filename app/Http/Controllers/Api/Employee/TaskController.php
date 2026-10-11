<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\SubmitTaskReportRequest;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $taskService)
    {
    }

    /**
     * GET /me/tasks
     */
    public function myTasks(Request $request)
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json(['data' => []]);
            }

            return response()->json(['data' => $this->taskService->myTasks($employee)]);
        } catch (Throwable $e) {
            Log::error('Erreur myTasks: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du chargement de vos tâches'], 500);
        }
    }

    /**
     * GET /tasks/{task}
     */
    public function show(Task $task)
    {
        try {
            $task->load(['employee.department', 'creator']);
            return response()->json(['success' => true, 'data' => $task]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement de la tâche'], 500);
        }
    }

    /**
     * POST /tasks/{task}/submit-report
     */
    public function submitReport(SubmitTaskReportRequest $request, Task $task)
    {
        try {
            $task = $this->taskService->submitReport($request->user(), $task, $request->file('report_file'));
            return response()->json(['message' => 'Rapport soumis avec succès', 'data' => $task]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Accès refusé'], 403);
        } catch (Throwable $e) {
            Log::error('Erreur soumission rapport: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur soumission rapport'], 500);
        }
    }

    /**
     * POST /tasks/{task}/mark-completed
     */
    public function markAsCompleted(Request $request, Task $task)
    {
        try {
            $task = $this->taskService->markAsCompleted($request->user(), $task);
            return response()->json(['message' => 'Terminée', 'data' => $task]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Accès refusé'], 403);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Erreur'], 400);
        }
    }

    /**
     * GET /tasks/{task}/download-file
     */
    public function downloadTaskFile(Task $task)
    {
        try {
            if (!$task->task_file || !Storage::disk('public')->exists($task->task_file)) {
                return response()->json(['message' => 'Fichier introuvable'], 404);
            }

            return Storage::disk('public')->download($task->task_file, "consignes-mission-{$task->id}.pdf");
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur de téléchargement'], 500);
        }
    }

    /**
     * GET /tasks/{task}/download-report
     */
    public function downloadReportFile(Task $task)
    {
        try {
            if (!$task->report_file || !Storage::disk('public')->exists($task->report_file)) {
                return response()->json(['message' => 'Rapport introuvable'], 404);
            }

            return Storage::disk('public')->download($task->report_file, "rapport-mission-{$task->id}.pdf");
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur de téléchargement'], 500);
        }
    }
}
