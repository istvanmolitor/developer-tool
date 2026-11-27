<?php

namespace App\Http\Requests;

use App\Services\AgentToolService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept the tools field as the list of tool names the form's checkboxes
     * produce (or a comma-separated string) and normalize it to a clean list
     * before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tools')) {
            $this->merge(['tools' => AgentToolService::normalize($this->input('tools'))]);
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
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('agents', 'slug')->ignore($this->route('agent')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'model' => ['nullable', 'string', 'max:255'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string', 'max:255'],
            'system_prompt' => ['required', 'string'],
            // Profile picture; raster images only, so it can be served inline.
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'A név nem tartalmazhat perjelet.',
            'slug.regex' => 'Az azonosító csak kisbetűket, számokat és kötőjelet tartalmazhat (pl. database-expert).',
            'avatar.image' => 'A profilkép csak kép lehet (JPG, PNG vagy WebP).',
            'avatar.max' => 'A profilkép legfeljebb 2 MB lehet.',
        ];
    }
}
