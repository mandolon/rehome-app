<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $message = \App\Models\TaskMessage::where('id', $this->route('messageId'))
            ->where('task_id', $this->route('taskId'))
            ->first();

        return $message && $this->user()->can('update', $message);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'content' => 'sometimes|required|string|max:10000',
            'metadata' => 'nullable|array',
            'metadata.*' => 'string|max:1000'
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Message content is required.',
            'content.string' => 'Message content must be a string.',
            'content.max' => 'Message content cannot exceed 10,000 characters.',
            'metadata.array' => 'Metadata must be an array.',
            'metadata.*.string' => 'Each metadata value must be a string.',
            'metadata.*.max' => 'Each metadata value cannot exceed 1,000 characters.'
        ];
    }
}