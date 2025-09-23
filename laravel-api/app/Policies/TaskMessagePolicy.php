<?php

namespace App\Policies;

use App\Models\TaskMessage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskMessagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any messages.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view messages
        return true;
    }

    /**
     * Determine whether the user can view the message.
     */
    public function view(User $user, TaskMessage $message): bool
    {
        // Admin can view any message
        if ($user->role === 'admin') {
            return true;
        }

        // Team members can view messages in their project tasks
        if ($user->role === 'team' && $message->task && $message->task->project_id) {
            return $user->hasProjectAccess($message->task->project_id);
        }

        // Users can view messages in tasks they have access to
        return $user->can('view', $message->task);
    }

    /**
     * Determine whether the user can create messages.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create messages
        return true;
    }

    /**
     * Determine whether the user can update the message.
     */
    public function update(User $user, TaskMessage $message): bool
    {
        // Admin can update any message
        if ($user->role === 'admin') {
            return true;
        }

        // Message author can update their own message
        return $message->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the message.
     */
    public function delete(User $user, TaskMessage $message): bool
    {
        // Admin can delete any message
        if ($user->role === 'admin') {
            return true;
        }

        // Message author can delete their own message
        return $message->user_id === $user->id;
    }
}