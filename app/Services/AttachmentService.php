<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\IncomingEmail;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\AttachmentRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores attached files for the application. Every file goes through {@see store()}.
 * A file belonging to a project is written into that project's own
 * `docs/attachments` folder (for the system project: under `storage`), so the
 * project physically holds all of its attachments; a project-less file (a mail
 * from a mailbox with no project) falls back to the private `local` disk. Files
 * arrive three ways: uploaded (task/web), synced from the folder, or saved from a
 * downloaded message. The stored file's absolute path is what an agent references.
 */
class AttachmentService
{
    private const DISK = 'local';

    public function __construct(private AttachmentRepository $attachments) {}

    /**
     * Absolute path to the project's attachments folder. The system project
     * documents the developer tool itself, so its files live under `storage`
     * rather than inside the application's own source tree.
     */
    public function directoryFor(Project $project): string
    {
        if ($project->is_system) {
            return storage_path('attachments');
        }

        return rtrim($project->path, '/').'/docs/attachments';
    }

    /**
     * A filesystem disk rooted at the project's attachments folder. The local
     * adapter creates the folder on first write, so uploads never need it to
     * exist up front.
     */
    public function diskFor(Project $project): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => $this->directoryFor($project),
        ]);
    }

    /**
     * Physically write the file to disk, then record it in the database. This is
     * the single path through which attachment bytes are stored. A project's files
     * go into its own folder; project-less files go on the private disk.
     *
     * @throws RuntimeException When the file cannot be written.
     */
    public function store(
        ?Project $project,
        string $contents,
        string $filename,
        ?string $mimeType = null,
        string $source = 'upload',
        ?string $relativePath = null,
    ): Attachment {
        if ($project !== null) {
            $disk = $this->diskFor($project);
            // Synced files keep their path relative to the folder; uploaded and
            // mail files get a collision-free name at the folder root.
            $path = $relativePath ?? $this->uniqueName($disk, $filename);

            if (! $disk->put($path, $contents)) {
                throw new RuntimeException("Nem sikerült menteni a(z) „{$filename}” csatolmányt.");
            }

            return $this->attachments->create([
                'project_id' => $project->id,
                'disk' => Attachment::PROJECT_DISK,
                'path' => $path,
                'filename' => $filename,
                'mime_type' => $mimeType ?: 'application/octet-stream',
                'size' => strlen($contents),
                'checksum' => hash('sha256', $contents),
                'source' => $source,
                // Every project file is tracked by its path within the folder, so
                // a later sync recognises it and never creates a duplicate row.
                'relative_path' => $path,
            ]);
        }

        $path = 'attachments/shared/'.$this->randomName($filename);

        if (! Storage::disk(self::DISK)->put($path, $contents)) {
            throw new RuntimeException("Nem sikerült menteni a(z) „{$filename}” csatolmányt.");
        }

        return $this->attachments->create([
            'project_id' => null,
            'disk' => self::DISK,
            'path' => $path,
            'filename' => $filename,
            'mime_type' => $mimeType ?: 'application/octet-stream',
            'size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'source' => $source,
            'relative_path' => null,
        ]);
    }

    /**
     * Store an uploaded file (web form or MCP) for a project.
     */
    public function storeUploadedFile(?Project $project, UploadedFile $file, string $source = 'upload'): Attachment
    {
        return $this->store(
            $project,
            (string) $file->get(),
            $file->getClientOriginalName() ?: $file->hashName(),
            $file->getClientMimeType(),
            $source,
        );
    }

    /**
     * Save a message's attachment and link it to the message. The project comes
     * from the message's mailbox, which may have none.
     */
    public function storeForEmail(IncomingEmail $email, string $filename, string $contents, ?string $mimeType = null): Attachment
    {
        $attachment = $this->store(
            $email->mailbox?->project,
            $contents,
            $filename,
            $mimeType,
            'mail',
        );

        $email->attachments()->attach($attachment->id);

        return $attachment;
    }

    /**
     * Attach a file to a task (idempotent).
     */
    public function attachToTask(Attachment $attachment, Task $task): void
    {
        $task->attachments()->syncWithoutDetaching([$attachment->id]);
    }

    /**
     * Reconcile the attachments table with the project's attachments folder:
     * every file not yet tracked by a row (by its path relative to the folder)
     * becomes a `source=sync` row; changed sync rows are refreshed; `source=sync`
     * rows whose file is gone are pruned (file and row). Uploaded and mail rows
     * are left untouched — they already track their own files — so sync never
     * duplicates or removes them.
     *
     * @return Collection<int, Attachment>
     */
    public function syncProject(Project $project): Collection
    {
        $directory = $this->directoryFor($project);
        $disk = $this->diskFor($project);

        $files = File::isDirectory($directory)
            ? collect($disk->allFiles())
            : collect();

        $tracked = $project->attachments()
            ->whereNotNull('relative_path')
            ->pluck('relative_path');

        foreach ($files as $path) {
            if (! $tracked->contains($path)) {
                $this->createSyncRow($project, $disk, $path);
            }
        }

        $project->attachments()
            ->where('source', 'sync')
            ->get()
            ->each(function (Attachment $attachment) use ($disk, $files): void {
                if (! $files->contains($attachment->relative_path)) {
                    $this->attachments->delete($attachment);

                    return;
                }

                $this->refreshIfChanged($attachment, $disk);
            });

        return $project->attachments()
            ->where('source', 'sync')
            ->orderBy('relative_path')
            ->get();
    }

    /**
     * Record a folder file the table does not yet know about as a sync row.
     */
    private function createSyncRow(Project $project, Filesystem $disk, string $path): Attachment
    {
        $contents = (string) $disk->get($path);

        return $this->attachments->create([
            'project_id' => $project->id,
            'disk' => Attachment::PROJECT_DISK,
            'path' => $path,
            'filename' => basename($path),
            'mime_type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'source' => 'sync',
            'relative_path' => $path,
        ]);
    }

    /**
     * Refresh a sync row's recorded size, checksum and mime type when its file's
     * contents have changed on disk.
     */
    private function refreshIfChanged(Attachment $attachment, Filesystem $disk): void
    {
        $contents = (string) $disk->get($attachment->relative_path);
        $checksum = hash('sha256', $contents);

        if ($attachment->checksum === $checksum) {
            return;
        }

        $this->attachments->update($attachment, [
            'size' => strlen($contents),
            'checksum' => $checksum,
            'mime_type' => $disk->mimeType($attachment->relative_path) ?: $attachment->mime_type,
        ]);
    }

    /**
     * A filename that does not collide with an existing file in the folder,
     * appending " (n)" before the extension when needed, so uploading the same
     * name twice yields two distinct files instead of silently overwriting one.
     */
    private function uniqueName(Filesystem $disk, string $filename): string
    {
        $filename = basename($filename);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $suffix = $extension !== '' ? ".{$extension}" : '';

        $candidate = $filename;
        $counter = 1;

        while ($disk->exists($candidate)) {
            $candidate = "{$name} ({$counter}){$suffix}";
            $counter++;
        }

        return $candidate;
    }

    /**
     * A random file name that keeps the original extension, for the shared disk
     * where project-less files live.
     */
    private function randomName(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = (string) Str::uuid();

        return $extension !== '' ? "{$name}.{$extension}" : $name;
    }
}
