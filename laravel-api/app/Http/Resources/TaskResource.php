<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'project_id' => $this->project_id,
            'project' => $this->project,
            'due_date' => $this->due_date?->toISOString(),
            'assignee' => $this->assignee,
            'tags' => $this->tags,
            'created_by' => $this->created_by,
            'deleted_by' => $this->deleted_by,
            'is_overdue' => $this->is_overdue,
            'formatted_due_date' => $this->formatted_due_date,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'archived_at' => $this->when(isset($this->archived_at), $this->archived_at?->toISOString()),
            'completed_at' => $this->when(isset($this->completed_at), $this->completed_at?->toISOString()),
            'messages' => TaskMessageResource::collection($this->whenLoaded('messages')),
            'creator' => new UserResource($this->whenLoaded('creator')),
        ];
    }
}