<?php

namespace App\Repositories\Contracts;

use App\Models\Document;

/**
 * Data access for {@see Document} rows.
 *
 * @extends Repository<Document>
 */
interface DocumentRepository extends Repository
{
    /**
     * Update the row for the given project and path, or create it when it does
     * not exist yet.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsertForProjectPath(int $projectId, string $path, array $attributes): Document;

    /**
     * Delete every row for the project whose id is not in the given set — used
     * to prune files that have since been removed from disk.
     *
     * @param  array<int, int>  $keepIds
     */
    public function deleteStaleForProject(int $projectId, array $keepIds): void;
}
