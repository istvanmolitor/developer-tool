<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', new Enum(TaskType::class)],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', new Enum(TaskStatus::class)],
            'auto_run' => ['boolean'],
            'priority' => ['nullable', new Enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
