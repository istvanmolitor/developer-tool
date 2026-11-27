<?php

namespace App\Repositories\Eloquent;

use App\Models\Document;
use App\Repositories\Contracts\DocumentRepository;

/**
 * @extends EloquentRepository<Document>
 */
class EloquentDocumentRepository extends EloquentRepository implements DocumentRepository
{
    protected function model(): string
    {
        return Document::class;
    }

    public function upsertForProjectPath(int $projectId, string $path, array $attributes): Document
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
