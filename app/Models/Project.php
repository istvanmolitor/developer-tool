<?php

namespace App\Models;

use App\Enums\ProjectProvisionStatus;
use App\Jobs\ProvisionProject;
use App\Services\Search\SearchService;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Database\Seeders\ProjectSeeder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Name of the single system project — the developer tool's own codebase.
     * Seeded by {@see ProjectSeeder}, never deletable.
     */
    public const SYSTEM_NAME = 'developer-tool';

    protected $fillable = [
        'name',
        'path',
        'git_url',
        'description',
    ];

    /**
     * `is_system` and `provision_status` are intentionally NOT fillable: the
     * former is set only by the seeder, the latter only by the controller and
     * the {@see ProvisionProject} job — neither can be set through a
     * create/update form.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'provision_status' => ProjectProvisionStatus::class,
        ];
    }

    /**
     * Guard the system project against deletion, even from tinker or the CLI.
     */
    protected static function booted(): void
    {
        static::deleting(function (Project $project): void {
            if ($project->is_system) {
                throw new \RuntimeException('A rendszer saját projektje nem törölhető.');
            }

            // The project's attachment files are removed here before its rows are
            // cascaded away, so no stored bytes are left orphaned on disk.
            $project->attachments()->each(fn (Attachment $attachment) => $attachment->delete());
        });

        // The database cascades the project's rows away without model events,
        // so their search documents are dropped in one go.
        static::deleted(function (Project $project): void {
            app(SearchService::class)->removeWhere('project_id', $project->id);
        });

        static::updating(function (Project $project): void {
            if ($project->is_system && $project->isDirty('path')) {
                throw new \RuntimeException('A rendszer saját projektjének útvonala nem módosítható.');
            }
        });
    }

    /**
     * Computed, filesystem-derived attributes appended to array/JSON output.
     *
     * @var list<string>
     */
    protected $appends = [
        'exists',
        'is_git_repository',
        'last_modified_at',
    ];

    /**
     * The agent contexts (claude conversations) run against this project.
     *
     * @return HasMany<AgentContext, $this>
     */
    public function agentContexts(): HasMany
    {
        return $this->hasMany(AgentContext::class);
    }

    /**
     * The prompt runs executed against this project, through their contexts.
     *
     * @return HasManyThrough<PromptRun, AgentContext, $this>
     */
    public function promptRuns(): HasManyThrough
    {
        return $this->hasManyThrough(PromptRun::class, AgentContext::class);
    }

    /**
     * Documentation files imported from this project's docs/documentations folder.
     *
     * @return HasMany<Documentation, $this>
     */
    public function documentations(): HasMany
    {
        return $this->hasMany(Documentation::class);
    }

    /**
     * Tasks tracked for this project.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Files stored for this project (uploaded, synced from docs/attachments, or
     * saved from a message).
     *
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Use the project name in URLs so links stay human readable.
     */
    public function getRouteKeyName(): string
    {
        return 'name';
    }

    /**
     * Whether the configured path currently exists on disk.
     *
     * @return Attribute<bool, never>
     */
    protected function exists(): Attribute
    {
        return Attribute::get(fn (): bool => is_dir($this->path));
    }

    /**
     * Whether the project directory is a git repository.
     *
     * @return Attribute<bool, never>
     */
    protected function isGitRepository(): Attribute
    {
        return Attribute::get(fn (): bool => is_dir($this->path.'/.git'));
    }

    /**
     * Last modification time of the project directory, if it exists.
     *
     * @return Attribute<?string, never>
     */
    protected function lastModifiedAt(): Attribute
    {
        return Attribute::get(function (): ?string {
            $modified = @filemtime($this->path);

            return $modified !== false
                ? CarbonImmutable::createFromTimestamp($modified)->toIso8601String()
                : null;
        });
    }
}
