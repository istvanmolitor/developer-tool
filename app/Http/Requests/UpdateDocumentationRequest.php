<?php

namespace App\Http\Requests;

use App\Models\Documentation;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentationRequest extends FormRequest
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
        /** @var Documentation $documentation */
        $documentation = $this->route('documentation');

        return [
            'path' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?![\/\\\\]).+\.md$/i',
                Rule::unique('documentations', 'path')
                    ->where('project_id', $project->id)
                    ->ignore($documentation->id),
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
