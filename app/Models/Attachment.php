<?php

namespace App\Models;

use App\Services\AttachmentService;
use Database\Factories\AttachmentFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A stored file, scoped to a project. The bytes physically live in the central
 * `attachments` folder beside the project directories, under the project's own
 * subfolder, recorded with `disk` = {@see PROJECT_DISK} and `path` relative to
 * that folder; project-less files (e.g. a mail from a mailbox with no project)
 * fall back to the private `local` disk. Tasks and messages reference the file
 * through their pivot tables, so a single attachment can be shared by several of
 * them. Rows are created through {@see AttachmentService}, never by writing to
 * disk directly.
 *
 * @property int $id
 * @property int|null $project_id
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $mime_type
 * @property int $size
 * @property string|null $checksum
 * @property string $source
 * @property string|null $relative_path
 */
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * Marker `disk` value for files stored inside a project's own folder. The
     * real filesystem root is derived from the project at access time by
     * {@see AttachmentService::diskFor()}, so it never has to be persisted.
     */
    public const PROJECT_DISK = 'project';

    protected $fillable = [
        'project_id',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'checksum',
        'source',
        'relative_path',
    ];

    protected $hidden = [
        'path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * Delete the physical file when the row is deleted, so storage never keeps
     * orphaned bytes.
     */
    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment): void {
            $attachment->storage()->delete($attachment->path);
        });
    }

    /**
     * The filesystem the bytes live on: a disk rooted at the project's own
     * attachments folder for {@see PROJECT_DISK} files, otherwise the named disk.
     */
    public function storage(): Filesystem
    {
        if ($this->disk === self::PROJECT_DISK && $this->project !== null) {
            return app(AttachmentService::class)->diskFor($this->project);
        }

        return Storage::disk($this->disk === self::PROJECT_DISK ? 'local' : $this->disk);
    }

    /**
     * The project this file belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The tasks this file is attached to.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)->withTimestamps();
    }

    /**
     * The downloaded messages this file arrived with.
     *
     * @return BelongsToMany<IncomingEmail, $this>
     */
    public function incomingEmails(): BelongsToMany
    {
        return $this->belongsToMany(IncomingEmail::class)->withTimestamps();
    }

    /**
     * Absolute filesystem path to the stored file. This is the stable path an
     * agent references when it needs to read the attachment.
     */
    public function absolutePath(): string
    {
        return $this->storage()->path($this->path);
    }

    /**
     * Stream the file as a download. Always sent as an attachment, never rendered
     * inline, so an uploaded HTML or SVG file cannot run in the app's origin.
     */
    public function toDownloadResponse(): StreamedResponse
    {
        $disk = $this->storage();

        abort_unless($disk->exists($this->path), 404);

        return $disk->download($this->path, $this->filename, [
            'Content-Type' => $this->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
