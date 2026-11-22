<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'description',
    ];

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
     * Prompt runs are linked by the (stable) project name rather than the id,
     * which keeps existing history intact and matches the queued job payload.
     *
     * @return HasMany<PromptRun, $this>
     */
    public function promptRuns(): HasMany
    {
        return $this->hasMany(PromptRun::class, 'project', 'name');
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
