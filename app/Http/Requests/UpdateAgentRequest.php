<?php

namespace App\Http\Requests;

use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept the tools field as a comma-separated string from the form and
     * normalize it to a clean list before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tools')) {
            $this->merge(['tools' => Agent::normalizeTools($this->input('tools'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\/\\\\]+$/',
                Rule::unique('agents', 'name')->ignore($this->route('agent')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'model' => ['nullable', 'string', 'max:255'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string', 'max:255'],
            'system_prompt' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'A név nem tartalmazhat perjelet.',
        ];
    }
}
