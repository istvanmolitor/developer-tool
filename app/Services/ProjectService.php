<?php

namespace App\Services;

use App\Support\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ProjectService
{
    /**
     * Absolute path to the directory that contains the projects.
     */
    public function projectsPath(): string
    {
        return rtrim((string) config('developer-tool.projects_path'), '/');
    }

    /**
     * List every project (immediate subdirectory) in the projects directory.
     *
     * @return Collection<int, Project>
     */
    public function all(): Collection
    {
        $base = $this->projectsPath();

        if (! is_dir($base)) {
            return collect();
        }

        return collect(scandir($base) ?: [])
            ->reject(fn (string $entry): bool => in_array($entry, ['.', '..'], true))
            ->filter(fn (string $entry): bool => is_dir($base.'/'.$entry))
            ->map(fn (string $entry): Project => $this->makeProject($entry))
            ->sortByDesc(fn (Project $project): int => $project->lastModifiedAt?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * Resolve a single project by its directory name, or null when it does
     * not exist. Rejects any name that would escape the projects directory.
     */
    public function find(string $name): ?Project
    {
        if (! $this->isValidName($name)) {
            return null;
        }

        if (! is_dir($this->projectsPath().'/'.$name)) {
            return null;
        }

        return $this->makeProject($name);
    }

    /**
     * Absolute filesystem path for a project directory name.
     */
    public function pathFor(string $name): string
    {
        return $this->projectsPath().'/'.$name;
    }

    private function makeProject(string $name): Project
    {
        $path = $this->pathFor($name);
        $modified = @filemtime($path);

        return new Project(
            name: $name,
            path: $path,
            lastModifiedAt: $modified !== false ? CarbonImmutable::createFromTimestamp($modified) : null,
            isGitRepository: is_dir($path.'/.git'),
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
