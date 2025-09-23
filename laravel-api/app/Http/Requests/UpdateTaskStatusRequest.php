<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $task = $this->route('taskId') ? 
            \App\Models\Task::where('task_id', $this->route('taskId'))->first() : 
            null;

        return $task && $this->user()->can('updateStatus', $task);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['redline', 'backlog', 'in_progress', 'in_review', 'completed'])
            ]
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Task status is required.',
            'status.in' => 'Task status must be one of: redline, backlog, in_progress, in_review, completed.'
        ];
    }
}