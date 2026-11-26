<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\TaskChange;
use App\Repositories\Contracts\AgentRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns task change log entries into display-ready rows with Hungarian labels.
 */
class TaskChangeFormatter
{
    public function __construct(private AgentRepository $agents) {}

    /**
     * Hungarian names of the tracked task fields.
     *
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'status' => 'Státusz',
        'priority' => 'Prioritás',
        'type' => 'Típus',
        'agent_id' => 'Felelős agent',
        'due_date' => 'Határidő',
    ];

    /**
     * Format the given entries. The entries' task, user and agent relations should be eager loaded.
     *
     * @param  iterable<TaskChange>  $entries
     * @return list<array{
     *     id: int,
     *     created_at: string|null,
     *     task: array{id: int, title: string}|null,
     *     actor: array{type: 'user'|'agent'|null, name: string|null},
     *     changes: list<array{field: string, label: string, old: string|null, new: string|null}>
     * }>
     */
    public function format(iterable $entries): array
    {
        $entries = collect($entries);
        $agentNames = $this->assignedAgentNames($entries);
        $rows = [];

        foreach ($entries as $entry) {
            $changes = [];

            foreach ($entry->changes as $field => $change) {
                $changes[] = [
                    'field' => $field,
                    'label' => self::FIELD_LABELS[$field] ?? $field,
                    'old' => $this->valueLabel($field, $change['old'] ?? null, $agentNames),
                    'new' => $this->valueLabel($field, $change['new'] ?? null, $agentNames),
                ];
            }

            $rows[] = [
                'id' => $entry->id,
                'created_at' => $entry->created_at?->toIso8601String(),
                'task' => $entry->relationLoaded('task')
                    ? ['id' => $entry->task->id, 'title' => $entry->task->title]
                    : null,
                'actor' => $this->actor($entry),
                'changes' => $changes,
            ];
        }

        return $rows;
    }

    /**
     * @return array{type: 'user'|'agent'|null, name: string|null}
     */
    private function actor(TaskChange $entry): array
    {
        if ($entry->user_id !== null) {
            return ['type' => 'user', 'name' => $entry->user?->name];
        }

        if ($entry->agent_id !== null) {
            return ['type' => 'agent', 'name' => $entry->agent?->name];
        }

        return ['type' => null, 'name' => null];
    }

    /**
     * Names of the agents that appear as old or new assignees, keyed by id, in one query.
     *
     * @param  Collection<int, TaskChange>  $entries
     * @return array<int, string>
     */
    private function assignedAgentNames(Collection $entries): array
    {
        $ids = $entries
            ->flatMap(fn (TaskChange $entry): array => [
                $entry->changes['agent_id']['old'] ?? null,
                $entry->changes['agent_id']['new'] ?? null,
            ])
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return $this->agents->namesByIds($ids->all());
    }

    /**
     * @param  array<int, string>  $agentNames
     */
    private function valueLabel(string $field, mixed $value, array $agentNames): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'status' => TaskStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'priority' => TaskPriority::tryFrom((string) $value)?->label() ?? (string) $value,
            'type' => TaskType::tryFrom((string) $value)?->label() ?? (string) $value,
            'agent_id' => $agentNames[(int) $value] ?? "Törölt agent (#{$value})",
            'due_date' => Carbon::parse((string) $value)->format('Y.m.d.'),
            default => (string) $value,
        };
    }
}
