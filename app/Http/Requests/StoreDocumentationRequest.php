<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentationRequest extends FormRequest
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
        /** @var Project $project */
        $project = $this->route('project');

        return [
            // A relative Markdown path within docs/documentations, unique per
            // project (so it lines up with what the folder import stores).
            'path' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?![\/\\\\]).+\.md$/i',
                Rule::unique('documentations', 'path')->where('project_id', $project->id),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'path.regex' => 'Az útvonalnak relatív .md fájlnak kell lennie (pl. README.md vagy guides/setup.md).',
            'path.unique' => 'Ezen a projekten már van ilyen útvonalú dokumentáció.',
        ];
    }
}
