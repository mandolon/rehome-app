<?php

namespace App\Http\Controllers;

use App\Models\TaskMessage;
use App\Models\Task;
use App\Http\Requests\UpdateTaskMessageRequest;
use App\Http\Resources\TaskMessageResource;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskMessageController extends Controller
{
    use ApiResponse, AuthorizesRequests;
    /**
     * Get all messages for a specific task
     */
    public function index(string $taskId): JsonResponse
    {
        $task = Task::where('task_id', $taskId)->firstOrFail();
        
        $this->authorize('view', $task);
        
        $messages = TaskMessage::byTask($taskId)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->resourceResponse(
            TaskMessageResource::collection($messages),
            'Task messages retrieved successfully'
        );
    }

    /**
     * Store a new message for a task
     */
    public function store(Request $request, string $taskId): JsonResponse
    {
        $task = Task::where('task_id', $taskId)->firstOrFail();

        $this->authorize('view', $task);
        $this->authorize('create', TaskMessage::class);

        $validated = $request->validate([
            'content' => 'required|string',
            'user_id' => 'required|integer|exists:users,id',
            'message_type' => 'nullable|string|in:comment,system,attachment',
            'metadata' => 'nullable|array'
        ]);

        $message = TaskMessage::create([
            'task_id' => $taskId,
            'user_id' => $validated['user_id'],
            'content' => $validated['content'],
            'message_type' => $validated['message_type'] ?? 'comment',
            'metadata' => $validated['metadata'] ?? null
        ]);

        $message->load('user');

        // Broadcast real-time update
        broadcast(new \App\Events\TaskMessageCreated($message));

        return $this->createdResponse(
            new TaskMessageResource($message),
            'Message created successfully'
        );
    }

    /**
     * Update a specific message
     */
    public function update(UpdateTaskMessageRequest $request, string $taskId, int $messageId): JsonResponse
    {
        $message = TaskMessage::where('id', $messageId)
            ->where('task_id', $taskId)
            ->firstOrFail();

        $validated = $request->validated();

        $message->update($validated);
        $message->load('user');

        // Broadcast real-time update
        broadcast(new \App\Events\TaskMessageUpdated($message));

        return $this->resourceResponse(
            new TaskMessageResource($message),
            'Message updated successfully'
        );
    }

    /**
     * Delete a specific message
     */
    public function destroy(string $taskId, int $messageId): JsonResponse
    {
        $message = TaskMessage::where('id', $messageId)
            ->where('task_id', $taskId)
            ->firstOrFail();

        $this->authorize('delete', $message);

        $message->delete();

        // Broadcast real-time update
        broadcast(new \App\Events\TaskMessageDeleted($messageId, $taskId));

        return $this->noContentResponse('Message deleted successfully');
    }
}