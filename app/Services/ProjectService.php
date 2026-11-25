<?php

namespace App\Services;

use App\Enums\ProjectProvisionStatus;
use App\Jobs\ProvisionProject;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\Rule;
use RuntimeException;

class ProjectService
{
    /**
     * Absolute path to the directory that contains the project directories
     * this tool can import from.
     */
    public function projectsPath(): string
    {
        return rtrim((string) config('developer-tool.projects_path'), '/');
    }

    /**
     * Every registered project, newest activity first.
     *
     * @return Collection<int, Project>
     */
    public function all(): Collection
    {
        return Project::query()->orderBy('name')->get();
    }

    /**
     * Resolve a single registered project by its (unique) name.
     */
    public function find(string $name): ?Project
    {
        if (! $this->isValidName($name)) {
            return null;
        }

        return Project::query()->where('name', $name)->first();
    }

    /**
     * Absolute filesystem path for a directory name inside the projects dir.
     */
    public function pathFor(string $name): string
    {
        return $this->projectsPath().'/'.$name;
    }

    /**
     * Immediate subdirectories of the projects directory. Used to import
     * existing project directories into the database.
     *
     * @return Collection<int, string>
     */
    public function scanDirectories(): Collection
    {
        $base = $this->projectsPath();

        if (! is_dir($base)) {
            return collect();
        }

        return collect(scandir($base) ?: [])
            ->reject(fn (string $entry): bool => in_array($entry, ['.', '..'], true))
            ->filter(fn (string $entry): bool => is_dir($base.'/'.$entry))
            ->values();
    }

    /**
     * Directory names inside the projects directory that exist on disk but are
     * not yet registered as a project. Feeds the "existing folder" dropdown of
     * the create form.
     *
     * @return list<string>
     */
    public function availableDirectories(): array
    {
        $existing = Project::query()->pluck('name')->all();

        return array_values(
            $this->scanDirectories()
                ->reject(fn (string $name): bool => in_array($name, $existing, true))
                ->all()
        );
    }

    /**
     * Whether a directory with the given (project) name already exists on disk
     * under the projects directory.
     */
    public function directoryExists(string $name): bool
    {
        return $this->isValidName($name) && is_dir($this->pathFor($name));
    }

    /**
     * Derive a folder name from a git repository URL, e.g.
     * `https://github.com/user/repo.git` or `git@github.com:user/repo.git`
     * both become `repo`.
     */
    public function deriveNameFromGitUrl(string $url): string
    {
        $trimmed = rtrim(trim($url), '/');
        $trimmed = (string) preg_replace('/\.git$/i', '', $trimmed);
        $segment = (string) preg_replace('#^.*[/:]#', '', $trimmed);

        return $segment;
    }

    /**
     * Resolve the project name and absolute path from the raw creation input
     * (the web form and the `create-project` MCP tool share this).
     *
     * `existing` takes the name from the directory, `git` from the explicit
     * name or else the repository URL, `new` only from the explicit name.
     *
     * @param  array<string, mixed>  $input
     * @return array{name: string, path: string}
     */
    public function resolveNameAndPath(array $input): array
    {
        $explicitName = trim((string) ($input['name'] ?? ''));
        $explicitName = $explicitName !== '' ? $explicitName : null;

        $name = match ($input['mode'] ?? null) {
            'existing' => (string) ($input['directory'] ?? ''),
            'git' => $explicitName ?? $this->deriveNameFromGitUrl((string) ($input['git_url'] ?? '')),
            default => $explicitName ?? '',
        };

        $name = trim($name);

        return [
            'name' => $name,
            'path' => $name !== '' ? $this->pathFor($name) : '',
        ];
    }

    /**
     * Validation rules for creating a project, applied to the input merged with
     * {@see resolveNameAndPath()}.
     *
     * @return array<string, mixed>
     */
    public function creationRules(?string $mode): array
    {
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
                    $mode === 'new',
                    [fn ($attribute, $value, $fail) => $this->directoryExists((string) $value)
                        && $fail('Ez a mappa már létezik a projektek könyvtárában.')],
                ),
            ],
            // Only the "existing" mode picks a directory; the form still sends
            // an empty value in the other modes, which must not be validated.
            'directory' => Rule::when(
                $mode === 'existing',
                ['required', Rule::in($this->availableDirectories())],
                ['nullable'],
            ),
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
    public function creationMessages(): array
    {
        return [
            'name.regex' => 'A név nem tartalmazhat perjelet.',
            'name.required' => 'Adj meg egy mappanevet (git link esetén a név a repóból is származhat).',
            'directory.required' => 'Válassz egy meglévő mappát.',
            'directory.in' => 'A kiválasztott mappa nem érhető el.',
        ];
    }

    /**
     * Persist a validated project and, for `new`/`git` modes, queue the
     * background job that creates (mkdir) or clones the directory. `existing`
     * points at a directory already on disk, so it is ready straight away.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createProject(array $validated): Project
    {
        $mode = $validated['mode'];
        $needsProvisioning = $mode !== 'existing';

        $project = new Project;
        $project->forceFill([
            'name' => $validated['name'],
            'path' => $validated['path'],
            'git_url' => $mode === 'git' ? ($validated['git_url'] ?? null) : null,
            'description' => $validated['description'] ?? null,
            'provision_status' => $needsProvisioning
                ? ProjectProvisionStatus::Pending
                : ProjectProvisionStatus::Ready,
        ])->save();

        if ($needsProvisioning) {
            ProvisionProject::dispatch($project);
        }

        return $project;
    }

    /**
     * Create an empty project directory. No-op if it already exists.
     */
    public function createDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Nem sikerült létrehozni a könyvtárat: {$path}");
        }
    }

    /**
     * Clone a git repository into the given target path.
     */
    public function cloneRepository(string $url, string $path): void
    {
        $result = Process::timeout($this->cloneTimeout())
            ->run(['git', 'clone', $url, $path]);

        if ($result->failed()) {
            throw new RuntimeException(
                trim($result->errorOutput()) ?: 'A git clone sikertelen volt.'
            );
        }
    }

    /**
     * Max seconds a `git clone` may run before timing out.
     */
    private function cloneTimeout(): int
    {
        return (int) config('developer-tool.clone_timeout', 900);
    }

    /**
     * Import any project directory that is not yet registered. Returns the
     * names that were created.
     *
     * @return list<string>
     */
    public function importFromDisk(): array
    {
        $existing = Project::query()->pluck('name')->all();

        return array_values(
            $this->scanDirectories()
                ->reject(fn (string $name): bool => in_array($name, $existing, true))
                ->map(function (string $name): string {
                    Project::create([
                        'name' => $name,
                        'path' => $this->pathFor($name),
                    ]);

                    return $name;
                })
                ->all()
        );
    }

    private function isValidName(string $name): bool
    {
        return $name !== ''
            && ! str_contains($name, '/')
            && ! str_contains($name, '\\')
            && $name !== '.'
            && $name !== '..';
    }
}
