<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromptRunRequest extends FormRequest
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
            'prompt' => ['nullable', 'string', 'min:3', 'max:20000', 'required_without:agent_id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            // Continue a given conversation of the user in this project instead of the latest one.
            'agent_context_id' => ['nullable', 'integer'],
        ];
    }
}
