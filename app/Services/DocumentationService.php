<?php

namespace App\Services;

use App\Models\Documentation;
use App\Models\Project;
use App\Repositories\Contracts\DocumentationRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Imports the Markdown documentation a project keeps under its
 * `docs/documentations` folder into the `documentations` table.
 */
class DocumentationService
{
    public function __construct(
        private MarkdownFileReader $reader,
        private DocumentationRepository $documentations,
    ) {}

    /**
     * Absolute path to the project's documentation folder.
     *
     * The system project documents the developer tool itself, so its Markdown
     * is read from the application's own folder (base_path()) rather than from
     * the stored project path.
     */
    public function directoryFor(Project $project): string
    {
        $base = $project->is_system ? base_path() : $project->path;

        return rtrim($base, '/').'/docs/documentations';
    }

    /**
     * Load every Markdown file under the project's `docs/documentations` folder
     * into the documentations table. Files are upserted by their path relative
     * to that folder, and rows whose files no longer exist are pruned, so the
     * table always mirrors the folder.
     *
     * @return Collection<int, Documentation>
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

                $document = $this->reader->read($file->getPathname());

                $documentation = $this->documentations->upsertForProjectPath(
                    $project->id,
                    $relativePath,
                    [
                        'title' => $this->resolveTitle($document, $relativePath),
                        'content' => $document->content,
                    ],
                );

                $seen[] = $documentation->id;
            }
        }

        // Drop rows for files that have since been removed (or all of them when
        // the folder is gone/empty — whereNotIn([]) matches every project row).
        $this->documentations->deleteStaleForProject($project->id, $seen);

        return $project->documentations()->orderBy('path')->get();
    }

    /**
     * Derive a human-readable title: front matter `title`/`name`, else the first
     * Markdown H1, else the file name without extension.
     */
    private function resolveTitle(ParsedMarkdown $document, string $relativePath): string
    {
        foreach (['title', 'name'] as $key) {
            $value = $document->matter($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        if (preg_match('/^\#\s+(.+)$/m', $document->content, $match)) {
            return trim($match[1]);
        }

        return pathinfo($relativePath, PATHINFO_FILENAME);
    }
}
