<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $taskService)
    {
    }

    /**
     * GET /tasks
     */
    public function index(Request $request)
    {
        try {
            $result = $this->taskService->listForStaff($request->user());

            if ($result === null) {
                return response()->json(['data' => [], 'message' => 'Manager sans département assigné']);
            }

            return response()->json($result);
        } catch (Throwable $e) {
            Log::error('Erreur index tâches: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /**
     * GET /manager/team-tasks
     */
    public function teamTasks(Request $request)
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json(['data' => [], 'message' => 'Profil employé introuvable.']);
            }

            return response()->json($this->taskService->teamTasks($employee));
        } catch (AuthorizationException $e) {
            return response()->json(['data' => [], 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('Erreur teamTasks: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /**
     * POST /tasks
     */
    public function store(StoreTaskRequest $request)
    {
        try {
            $task = $this->taskService->create($request->user(), $request->validated(), $request->file('task_file'));
            return response()->json(['message' => 'Mission créée avec succès', 'data' => $task], 201);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            Log::error('Erreur création tâche: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la création', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /tasks/{task}
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        try {
            $task = $this->taskService->update($request->user(), $task, $request->validated(), $request->file('task_file'));
            return response()->json(['message' => 'Mise à jour réussie', 'data' => $task]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Accès refusé'], 403);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur'], 500);
        }
    }
}
