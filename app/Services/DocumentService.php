<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Project;
use App\Repositories\Contracts\DocumentRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Imports the Markdown files a project keeps under its `docs` folder — the
 * whole tree, subfolders (such as `docs/documentations`) included — into the
 * `documents` table.
 */
class DocumentService
{
    public function __construct(
        private MarkdownFileReader $reader,
        private DocumentRepository $documents,
    ) {}

    /**
     * Absolute path to the project's `docs` folder — the root of every document.
     *
     * The system project documents the developer tool itself, so its Markdown
     * is read from the application's own folder (base_path()) rather than from
     * the stored project path.
     */
    public function directoryFor(Project $project): string
    {
        $base = $project->is_system ? base_path() : $project->path;

        return rtrim($base, '/').'/docs';
    }

    /**
     * Load every Markdown file under the project's `docs` folder — read
     * recursively, so documents in subfolders (e.g. `docs/documentations`,
     * written by the documentarian agent) come along — into the documents
     * table. Files are upserted by their path relative to `docs`, and rows
     * whose files no longer exist are pruned, so the table always mirrors the
     * folder.
     *
     * @return Collection<int, Document>
     */
    public function syncProject(Project $project): Collection
    {
        $directory = $this->directoryFor($project);

        $seen = [];

        if (File::isDirectory($directory)) {
            foreach (File::allFiles($directory) as $file) {
                if (strtolower($file->getExtension()) !== 'md') {
                    continue;
                }

                $relativePath = ltrim(
                    str_replace($directory, '', $file->getPathname()),
                    DIRECTORY_SEPARATOR,
                );

                $parsed = $this->reader->read($file->getPathname());

                $document = $this->documents->upsertForProjectPath(
                    $project->id,
                    $relativePath,
                    [
                        'title' => $this->resolveTitle($parsed, $relativePath),
                        'content' => $parsed->content,
                    ],
                );

                $seen[] = $document->id;
            }
        }

        // Drop rows for files that have since been removed (or all of them when
        // the folder is gone/empty — whereNotIn([]) matches every project row).
        $this->documents->deleteStaleForProject($project->id, $seen);

        return $project->documents()->orderBy('path')->get();
    }

    /**
     * Derive a human-readable title: front matter `title`/`name`, else the first
     * Markdown H1, else the file name without extension.
     */
    private function resolveTitle(ParsedMarkdown $parsed, string $relativePath): string
    {
        foreach (['title', 'name'] as $key) {
            $value = $parsed->matter($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        if (preg_match('/^\#\s+(.+)$/m', $parsed->content, $match)) {
            return trim($match[1]);
        }

        return pathinfo($relativePath, PATHINFO_FILENAME);
    }
}
