<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view tasks
        return true;
    }

    /**
     * Determine whether the user can view all tasks (including deleted).
     */
    public function viewAll(User $user): bool
    {
        // Only admins can view all tasks including deleted ones
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        // Admin can view any task
        if ($user->role === 'admin') {
            return true;
        }

        // Team members can view tasks in their projects
        if ($user->role === 'team' && $task->project_id) {
            return $user->hasProjectAccess($task->project_id);
        }

        // Clients can only view tasks they created or are assigned to
        if ($user->role === 'client') {
            return $task->created_by === $user->id || $task->assignee === $user->email;
        }

        return false;
    }

    /**
     * Determine whether the user can create tasks.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create tasks
        return true;
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        // Admin can update any task
        if ($user->role === 'admin') {
            return true;
        }

        // Team members can update tasks in their projects
        if ($user->role === 'team' && $task->project_id) {
            return $user->hasProjectAccess($task->project_id);
        }

        // Task creator can always update their task
        return $task->created_by === $user->id;
    }

    /**
     * Determine whether the user can update task status.
     */
    public function updateStatus(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /**
     * Determine whether the user can archive the task.
     */
    public function archive(User $user, Task $task): bool
    {
        // Admin can archive any task
        if ($user->role === 'admin') {
            return true;
        }

        // Team members can archive tasks in their projects
        if ($user->role === 'team' && $task->project_id) {
            return $user->hasProjectAccess($task->project_id);
        }

        // Task creator can archive their task
        return $task->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the task (soft delete).
     */
    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /**
     * Determine whether the user can restore the task.
     */
    public function restore(User $user, Task $task): bool
    {
        // Admin can restore any task
        if ($user->role === 'admin') {
            return true;
        }

        // Team members can restore tasks in their projects
        if ($user->role === 'team' && $task->project_id) {
            return $user->hasProjectAccess($task->project_id);
        }

        // Task creator can restore their task
        return $task->created_by === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the task.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        // Only admins can permanently delete tasks
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can view project tasks.
     */
    public function viewProjectTasks(User $user, string $projectId): bool
    {
        // Admin can view all project tasks
        if ($user->role === 'admin') {
            return true;
        }

        // Team members can view tasks in their projects
        if ($user->role === 'team') {
            return $user->hasProjectAccess($projectId);
        }

        // Clients can only view tasks in projects they have access to
        if ($user->role === 'client') {
            return $user->hasProjectAccess($projectId);
        }

        return false;
    }
}