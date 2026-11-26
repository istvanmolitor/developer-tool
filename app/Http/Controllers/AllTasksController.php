<?php

namespace App\Http\Controllers;

use App\Enums\SearchType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AllTasksController extends Controller
{
    /**
     * Every task in the system, across all projects, from the search index:
     * filterable by free text, project, status, priority, type and assignee,
     * sortable and paginated.
     */
    public function index(Request $request, SearchService $search): Response
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'project' => $request->integer('project') ?: null,
            'status' => TaskStatus::tryFrom($request->string('status')->toString())?->value,
            'priority' => TaskPriority::tryFrom($request->string('priority')->toString())?->value,
            'type' => TaskType::tryFrom($request->string('type')->toString())?->value,
            'agent' => $request->integer('agent') ?: null,
        ];

        $sort = $this->resolveSort($request->string('sort')->toString());
        [$sortField, $sortDirection] = self::SORTS[$sort];

        $results = $search->search(
            SearchCriteria::for(SearchType::Task)
                ->matching($filters['q'])
                ->where('project_id', $filters['project'])
                ->where('status', $filters['status'])
                ->where('priority', $filters['priority'])
                ->where('task_type', $filters['type'])
                ->where('agent_ids', $filters['agent'])
                ->orderBy($sortField, $sortDirection)
                ->paginate($request->integer('page', 1), 20),
        );

        return Inertia::render('Tasks/Index', [
            'tasks' => $results->paginate(
                Task::query()->with(['agent:id,name', 'project:id,name']),
                $request,
            ),
            'filters' => [...$filters, 'sort' => $sort],
            'search_degraded' => $results->degraded,
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'types' => $this->typeOptions(),
            'agents' => $this->agentOptions(),
            'projects' => $this->projectOptions(),
            'sorts' => $this->sortOptions(),
        ]);
    }

    /**
     * The sort keys offered on the page mapped to the indexed field and
     * direction they order by. The first key is the default.
     */
    private const SORTS = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'updated' => ['updated_at', 'desc'],
        'due' => ['due_date', 'asc'],
        'priority' => ['priority_rank', 'asc'],
        'title' => ['title', 'asc'],
    ];

    /**
     * Hungarian labels for the sort keys, in the order they appear.
     *
     * @var array<string, string>
     */
    private const SORT_LABELS = [
        'newest' => 'Legújabb elöl',
        'oldest' => 'Legrégebbi elöl',
        'updated' => 'Legutóbb módosított',
        'due' => 'Határidő szerint',
        'priority' => 'Prioritás szerint',
        'title' => 'Cím szerint (A–Z)',
    ];

    private function resolveSort(string $sort): string
    {
        return array_key_exists($sort, self::SORTS) ? $sort : array_key_first(self::SORTS);
    }

    /**
     * Sort keys with Hungarian labels for the frontend select.
     *
     * @return list<array{value: string, label: string}>
     */
    private function sortOptions(): array
    {
        return array_map(
            fn (string $key, string $label): array => ['value' => $key, 'label' => $label],
            array_keys(self::SORT_LABELS),
            array_values(self::SORT_LABELS),
        );
    }

    /**
     * Status values with Hungarian labels for the frontend selects.
     *
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (TaskStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            TaskStatus::cases(),
        );
    }

    /**
     * Priority values with Hungarian labels for the frontend selects.
     *
     * @return list<array{value: string, label: string}>
     */
    private function priorityOptions(): array
    {
        return array_map(
            fn (TaskPriority $priority): array => ['value' => $priority->value, 'label' => $priority->label()],
            TaskPriority::cases(),
        );
    }

    /**
     * Type values with Hungarian labels for the frontend selects and badges.
     *
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (TaskType $type): array => ['value' => $type->value, 'label' => $type->label()],
            TaskType::cases(),
        );
    }

    /**
     * Assignable agents for the assignee filter.
     *
     * @return list<array{id: int, name: string}>
     */
    private function agentOptions(): array
    {
        $options = [];

        foreach (Agent::query()->orderBy('name')->get(['id', 'name']) as $agent) {
            $options[] = ['id' => $agent->id, 'name' => $agent->name];
        }

        return $options;
    }

    /**
     * Projects for the project filter.
     *
     * @return list<array{id: int, name: string}>
     */
    private function projectOptions(): array
    {
        $options = [];

        foreach (Project::query()->orderBy('name')->get(['id', 'name']) as $project) {
            $options[] = ['id' => $project->id, 'name' => $project->name];
        }

        return $options;
    }
}
