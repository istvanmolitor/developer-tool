<?php

namespace App\Models;

use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'model',
        'tools',
        'system_prompt',
        'source_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tools' => 'array',
        ];
    }

    /**
     * The prompt runs executed as this agent.
     *
     * @return HasMany<PromptRun, $this>
     */
    public function promptRuns(): HasMany
    {
        return $this->hasMany(PromptRun::class);
    }

    /**
     * The tasks assigned to this agent.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * The mailboxes this agent sends and receives mail through.
     *
     * @return HasMany<Mailbox, $this>
     */
    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }

    /**
     * Normalize a tools value into a clean list of tool names, accepting either
     * a comma-separated string (as used in the agent form and the `.md` front
     * matter) or an already-split array. Returns null when nothing remains.
     *
     * @return list<string>|null
     */
    public static function normalizeTools(mixed $tools): ?array
    {
        if (is_string($tools)) {
            $tools = explode(',', $tools);
        }

        if (! is_array($tools)) {
            return null;
        }

        $normalized = array_values(array_filter(
            array_map(fn ($tool): string => trim((string) $tool), $tools),
            fn (string $tool): bool => $tool !== '',
        ));

        return $normalized === [] ? null : $normalized;
    }
}
