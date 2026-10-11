<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\TaskService;
use Throwable;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $taskService)
    {
    }

    public function destroy(Task $task)
    {
        try {
            $this->taskService->delete($task);
            return response()->json(['message' => 'Supprimée']);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }
}
