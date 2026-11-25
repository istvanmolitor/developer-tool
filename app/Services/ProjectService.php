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
        $explicitName = $this->stringInput($input, 'name');
        $explicitName = $explicitName !== '' ? $explicitName : null;

        $name = match ($input['mode'] ?? null) {
            'existing' => $this->stringInput($input, 'directory'),
            'git' => $explicitName ?? $this->deriveNameFromGitUrl($this->stringInput($input, 'git_url')),
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
     * `$mode` is the raw, not yet validated input (it may be any type); it is
     * only ever compared strictly, the `mode` rule reports invalid values.
     *
     * @return array<string, mixed>
     */
    public function creationRules(mixed $mode): array
    {
        return [
            'mode' => ['required', Rule::in(['existing', 'new', 'git'])],
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\/\\\\]+$/',
                // `.`/`..` and control characters would escape or corrupt the
                // projects directory path.
                fn ($attribute, $value, $fail) => is_string($value) && ! $this->isValidName($value)
                    && $fail('Érvénytelen mappanév.'),
                Rule::unique('projects', 'name'),
                // A folder that is created (mkdir) or cloned into must not
                // already exist on disk.
                Rule::when(
                    in_array($mode, ['new', 'git'], true),
                    [fn ($attribute, $value, $fail) => is_string($value) && $this->directoryExists($value)
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
            'git_url' => [
                'nullable',
                'string',
                'max:1024',
                fn ($attribute, $value, $fail) => is_string($value) && ! $this->isAllowedGitUrl(trim($value))
                    && $fail('Csak https://, ssh:// vagy user@host:útvonal formátumú git link adható meg.'),
            ],
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
     * Persist a validated project and make its directory available.
     *
     * Only a `git clone` can be slow (or fail), so that alone is deferred to
     * the background {@see ProvisionProject} job (status `pending`). An empty
     * folder (`new` mode, or `git` mode without a URL) is created with a plain
     * `mkdir` right here, so the directory exists the moment the request
     * returns instead of a queue worker round-trip later. `existing` points at
     * a directory already on disk, so it is ready with no filesystem work.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createProject(array $validated): Project
    {
        $mode = $validated['mode'];
        $gitUrl = $mode === 'git' ? $this->nullableTrimmed($validated['git_url'] ?? null) : null;

        // A clone is the only slow/failure-prone step; everything else is an
        // instant mkdir (or nothing, for `existing`).
        $needsClone = $gitUrl !== null;

        $project = new Project;
        $project->forceFill([
            'name' => $validated['name'],
            'path' => $validated['path'],
            'git_url' => $gitUrl,
            'description' => $this->nullableTrimmed($validated['description'] ?? null),
            'provision_status' => $needsClone
                ? ProjectProvisionStatus::Pending
                : ProjectProvisionStatus::Ready,
        ])->save();

        if ($needsClone) {
            ProvisionProject::dispatch($project);
        } elseif ($mode !== 'existing') {
            $this->provisionDirectory($project);
        }

        return $project;
    }

    /**
     * Create the empty project directory synchronously, recording a failure on
     * the project (instead of throwing) so a filesystem error cannot 500 the
     * request that created the project row.
     */
    private function provisionDirectory(Project $project): void
    {
        try {
            $this->createDirectory($project->path);
        } catch (\Throwable $e) {
            $project->forceFill([
                'provision_status' => ProjectProvisionStatus::Failed,
                'provision_error' => $e->getMessage(),
            ])->save();
        }
    }

    /**
     * Create an empty project directory. No-op if it already exists.
     */
    public function createDirectory(string $path): void
    {
        $this->ensureProjectPath($path);

        if (is_dir($path)) {
            return;
        }

        if (! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Nem sikerült létrehozni a könyvtárat: {$path}");
        }
    }

    /**
     * Clone a git repository into the given target path.
     *
     * The URL is re-checked here (not only at validation) and passed after
     * `--`, so it can never be parsed as a git option; git itself is limited
     * to the https/ssh transports (no file://, ext::, local paths).
     */
    public function cloneRepository(string $url, string $path): void
    {
        if (! $this->isAllowedGitUrl($url)) {
            throw new RuntimeException('Nem engedélyezett git link.');
        }

        $this->ensureProjectPath($path);

        $result = Process::timeout($this->cloneTimeout())
            ->env(['GIT_ALLOW_PROTOCOL' => 'https:ssh'])
            ->run(['git', 'clone', '--', $url, $path]);

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

    /**
     * Whether the given git URL uses an allowed transport: `https://`,
     * `ssh://` or the scp-like `user@host:path`. Option-like values (`-…`),
     * `file://`, `ext::` and local paths are rejected.
     */
    public function isAllowedGitUrl(string $url): bool
    {
        $host = '[A-Za-z0-9][A-Za-z0-9.-]*';
        $visible = '[^\s\x00-\x1F\x7F]';

        $isUrl = preg_match(
            '#^(https|ssh)://([^@/\s\x00-\x1F\x7F]+@)?'.$host.'(:\d+)?/'.$visible.'+$#D',
            $url,
        ) === 1;

        $isScpLike = preg_match(
            '#^[A-Za-z0-9_][A-Za-z0-9._-]*@'.$host.':[^\s\x00-\x1F\x7F-]'.$visible.'*$#D',
            $url,
        ) === 1;

        return $isUrl || $isScpLike;
    }

    /**
     * Guard against provisioning outside the projects directory: the path's
     * parent must be exactly {@see projectsPath()} and its last segment a
     * valid folder name.
     */
    private function ensureProjectPath(string $path): void
    {
        if (dirname($path) !== $this->projectsPath() || ! $this->isValidName(basename($path))) {
            throw new RuntimeException("A projekt útvonala a projektek könyvtárán kívülre mutat: {$path}");
        }
    }

    /**
     * Trimmed string input value, or `''` when missing or not a string.
     *
     * @param  array<string, mixed>  $input
     */
    private function stringInput(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    }

    /**
     * Trim a string and turn an empty one into null (the MCP path has no
     * TrimStrings/ConvertEmptyStringsToNull middleware).
     */
    private function nullableTrimmed(mixed $value): ?string
    {
        $trimmed = is_string($value) ? trim($value) : '';

        return $trimmed !== '' ? $trimmed : null;
    }

    private function isValidName(string $name): bool
    {
        return $name !== ''
            && ! str_contains($name, '/')
            && ! str_contains($name, '\\')
            && $name !== '.'
            && $name !== '..'
            && preg_match('/[\x00-\x1F\x7F]/', $name) !== 1;
    }
}
