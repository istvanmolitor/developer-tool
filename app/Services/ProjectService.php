<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Collection;

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
     * Import any project directory that is not yet registered. Returns the
     * names that were created.
     *
     * @return list<string>
     */
    public function importFromDisk(): array
    {
        $existing = Project::query()->pluck('name')->all();

        return $this->scanDirectories()
            ->reject(fn (string $name): bool => in_array($name, $existing, true))
            ->map(function (string $name): string {
                Project::create([
                    'name' => $name,
                    'path' => $this->pathFor($name),
                ]);

                return $name;
            })
            ->values()
            ->all();
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
