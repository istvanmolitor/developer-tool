<?php

namespace App\Repositories\Eloquent;

use App\Models\Documentation;
use App\Repositories\Contracts\DocumentationRepository;

/**
 * @extends EloquentRepository<Documentation>
 */
class EloquentDocumentationRepository extends EloquentRepository implements DocumentationRepository
{
    protected function model(): string
    {
        return Documentation::class;
    }

    public function upsertForProjectPath(int $projectId, string $path, array $attributes): Documentation
    {
        return $this->newQuery()->updateOrCreate(
            ['project_id' => $projectId, 'path' => $path],
            $attributes,
        );
    }

    public function deleteStaleForProject(int $projectId, array $keepIds): void
    {
        // Deleted one by one so each leaves the search index too.
        $this->newQuery()
            ->where('project_id', $projectId)
            ->whereNotIn('id', $keepIds)
            ->get()
            ->each
            ->delete();
    }
}
