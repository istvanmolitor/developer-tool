<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Database\Seeders\ProjectSeeder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * `is_system` is intentionally NOT fillable: it is set only by the seeder,
     * so it can never be flipped through the create/update forms.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
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
     * The prompt runs executed against this project.
     *
     * @return HasMany<PromptRun, $this>
     */
    public function promptRuns(): HasMany
    {
        return $this->hasMany(PromptRun::class);
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
