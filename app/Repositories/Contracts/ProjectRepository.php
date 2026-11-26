<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for {@see Project} rows.
 *
 * @extends Repository<Project>
 */
interface ProjectRepository extends Repository
{
    /**
     * Every project, alphabetical, with its prompt-run count (`runs_count`) and
     * last-run timestamp (`last_run_at`) — the projects index.
     *
     * @return Collection<int, Project>
     */
    public function allWithRunStats(): Collection;

    /**
     * Every project, alphabetical.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, Project>
     */
    public function allOrderedByName(array $columns = ['*']): Collection;

    /**
     * Every project, alphabetical, with its task count (`tasks_count`).
     *
     * @return Collection<int, Project>
     */
    public function allWithTaskCounts(): Collection;

    /**
     * Resolve a single project by its unique name.
     */
    public function findByName(string $name): ?Project;

    /**
     * Resolve a project by name, falling back to a numeric id.
     */
    public function resolveByNameOrId(string $identifier): ?Project;

    /**
     * Every registered project name.
     *
     * @return array<int, string>
     */
    public function names(): array;

    /**
     * Project names keyed by id, for the given id set.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, string>
     */
    public function namesByIds(array $ids): array;

    /**
     * Projects for the given id set, keyed by id.
     *
     * @param  array<int, int|string>  $ids
     * @param  array<int, string>  $columns
     * @return Collection<int, Project>
     */
    public function byIdsKeyed(array $ids, array $columns = ['*']): Collection;
}
