<?php

namespace App\Http\Requests;

use App\Services\ProjectService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A project is created in one of three modes:
     *
     *  - `existing`: pick a directory that already lives under the projects
     *    path. The `directory` field names it; nothing is created on disk.
     *  - `new`: a not-yet-existing folder name. The folder is created (mkdir).
     *  - `git`: a repository link. The folder is created with `git clone`; its
     *    name defaults to the repo name but can be overridden.
     *
     * In every mode the stored `path` is derived here from the projects path
     * and the resolved name — the user only ever gives the inner folder name.
     *
     * Resolve the project name and absolute path before validation so the
     * usual unique/regex rules can run against the derived name.
     */
    protected function prepareForValidation(): void
    {
        $service = app(ProjectService::class);

        $name = match ($this->input('mode')) {
            'existing' => (string) $this->input('directory', ''),
            'git' => $this->filledName() ?? $service->deriveNameFromGitUrl((string) $this->input('git_url', '')),
            default => $this->filledName() ?? '',
        };

        $name = trim($name);

        $this->merge([
            'name' => $name,
            'path' => $name !== '' ? $service->pathFor($name) : '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = app(ProjectService::class);

        return [
            'mode' => ['required', Rule::in(['existing', 'new', 'git'])],
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\/\\\\]+$/',
                Rule::unique('projects', 'name'),
                // A brand new folder must not already exist on disk.
                Rule::when(
                    $this->input('mode') === 'new',
                    [fn ($attribute, $value, $fail) => $service->directoryExists((string) $value)
                        && $fail('Ez a mappa már létezik a projektek könyvtárában.')],
                ),
            ],
            'directory' => [
                Rule::requiredIf($this->input('mode') === 'existing'),
                Rule::in($service->availableDirectories()),
            ],
            // The git link is always optional: an empty git-mode project simply
            // gets an empty folder (mkdir) instead of a clone.
            'git_url' => ['nullable', 'string', 'max:1024'],
            'path' => ['required', 'string', 'max:1024'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'A név nem tartalmazhat perjelet.',
            'name.required' => 'Adj meg egy mappanevet (git link esetén a név a repóból is származhat).',
            'directory.required' => 'Válassz egy meglévő mappát.',
            'directory.in' => 'A kiválasztott mappa nem érhető el.',
        ];
    }

    /**
     * The `name` field as a trimmed non-empty string, or null when absent.
     */
    private function filledName(): ?string
    {
        $name = trim((string) $this->input('name', ''));

        return $name !== '' ? $name : null;
    }
}
