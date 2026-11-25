<?php

namespace App\Http\Requests;

use App\Services\ProjectService;
use Illuminate\Foundation\Http\FormRequest;

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
        $this->merge(app(ProjectService::class)->resolveNameAndPath($this->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return app(ProjectService::class)->creationRules($this->input('mode'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(ProjectService::class)->creationMessages();
    }
}
