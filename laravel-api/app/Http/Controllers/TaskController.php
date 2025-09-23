<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    /**
     * Display a listing of tasks
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::with(['messages', 'creator'])
            ->active()
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('project_id')) {
            $query->byProject($request->project_id);
        }

        if ($request->has('include_deleted') && $request->boolean('include_deleted')) {
            $this->authorize('viewAll', Task::class);
            $query->withTrashed();
        }

        $tasks = $query->get();

        return $this->resourceResponse(
            TaskResource::collection($tasks),
            'Tasks retrieved successfully'
        );
    }

    /**
     * Get all tasks including deleted ones
     */
    public function allTasks(): JsonResponse
    {
        $this->authorize('viewAll', Task::class);

        $tasks = Task::withTrashed()
            ->with(['messages', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->resourceResponse(
            TaskResource::collection($tasks),
            'All tasks retrieved successfully'
        );
    }

    /**
     * Store a newly created task
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $validated = $request->validate([
            'task_id' => 'required|string|unique:tasks,task_id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:redline,backlog,in_progress,in_review,completed',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'project_id' => 'nullable|string',
            'project' => 'nullable|string',
            'due_date' => 'nullable|date',
            'assignee' => 'nullable|string',
            'tags' => 'nullable|array',
            'created_by' => 'nullable|string'
        ]);

        $task = Task::create($validated);
        $task->load(['messages', 'creator']);

        // Broadcast real-time update
        broadcast(new \App\Events\TaskCreated($task));

        return $this->createdResponse(
            new TaskResource($task),
            'Task created successfully'
        );
    }

    /**
     * Display the specified task
     */
    public function show(string $taskId): JsonResponse
    {
        $task = Task::with(['messages.user', 'creator'])
            ->where('task_id', $taskId)
            ->firstOrFail();

        $this->authorize('view', $task);

        return $this->resourceResponse(
            new TaskResource($task),
            'Task retrieved successfully'
        );
    }

    /**
     * Update the specified task
     */
    public function update(Request $request, string $taskId): JsonResponse
    {
        $task = Task::where('task_id', $taskId)->firstOrFail();
        
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:redline,backlog,in_progress,in_review,completed',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'project_id' => 'nullable|string',
            'project' => 'nullable|string',
            'due_date' => 'nullable|date',
            'assignee' => 'nullable|string',
            'tags' => 'nullable|array'
        ]);

        $task->update($validated);
        $task->load(['messages', 'creator']);

        // Broadcast real-time update
        broadcast(new \App\Events\TaskUpdated($task));

        return $this->resourceResponse(
            new TaskResource($task),
            'Task updated successfully'
        );
    }

    /**
     * Soft delete the specified task
     */
    public function destroy(string $taskId): JsonResponse
    {
        $task = Task::where('task_id', $taskId)->firstOrFail();
        
        $this->authorize('delete', $task);
        
        $task->delete();

        // Broadcast real-time update
        broadcast(new \App\Events\TaskDeleted($taskId));

        return $this->noContentResponse('Task deleted successfully');
    }

    /**
     * Permanently delete the specified task
     */
    public function forceDestroy(string $taskId): JsonResponse
    {
        $task = Task::withTrashed()
            ->where('task_id', $taskId)
            ->firstOrFail();
        
        $this->authorize('forceDelete', $task);
        
        $task->forceDelete();

        // Broadcast real-time update
        broadcast(new \App\Events\TaskPermanentlyDeleted($taskId));

        return $this->noContentResponse('Task permanently deleted');
    }

    /**
     * Restore a soft deleted task
     */
    public function restore(string $taskId): JsonResponse
    {
        $task = Task::withTrashed()
            ->where('task_id', $taskId)
            ->firstOrFail();
        
        $this->authorize('restore', $task);
        
        $task->restore();
        $task->load(['messages', 'creator']);

        // Broadcast real-time update
        broadcast(new \App\Events\TaskRestored($task));

        return $this->resourceResponse(
            new TaskResource($task),
            'Task restored successfully'
        );
    }

    /**
     * Update task status
     */
    public function updateStatus(UpdateTaskStatusRequest $request, string $taskId): JsonResponse
    {
        $task = Task::where('task_id', $taskId)->firstOrFail();
        
        $validated = $request->validated();

        if ($validated['status'] === 'completed') {
            $task->markAsCompleted(auth()->id());
        } else {
            $task->update(['status' => $validated['status']]);
        }

        $task->load(['messages', 'creator']);

        // Broadcast real-time update
        broadcast(new \App\Events\TaskUpdated($task));

        return $this->resourceResponse(
            new TaskResource($task),
            'Task status updated successfully'
        );
    }

    /**
     * Archive a task
     */
    public function archive(string $taskId): JsonResponse
    {
        $task = Task::where('task_id', $taskId)->firstOrFail();
        
        $this->authorize('archive', $task);
        
        $task->archive(auth()->id());

        // Broadcast real-time update
        broadcast(new \App\Events\TaskArchived($task));

        return $this->noContentResponse('Task archived successfully');
    }

    /**
     * Get tasks by project
     */
    public function byProject(string $projectId): JsonResponse
    {
        $this->authorize('viewProjectTasks', [Task::class, $projectId]);

        $tasks = Task::byProject($projectId)
            ->with(['messages', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->resourceResponse(
            TaskResource::collection($tasks),
            'Project tasks retrieved successfully'
        );
    }
}