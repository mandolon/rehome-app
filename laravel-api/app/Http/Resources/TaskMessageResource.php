<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskMessageResource extends JsonResource
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
            'content' => $this->content,
            'message_type' => $this->message_type,
            'metadata' => $this->metadata,
            'formatted_content' => $this->formatted_content,
            'time_ago' => $this->time_ago,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'user' => new UserResource($this->whenLoaded('user')),
            'task' => new TaskResource($this->whenLoaded('task')),
        ];
    }
}