<?php

namespace App\Http\Controllers;

use App\Enums\SearchType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Agent;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchService;
use App\Services\TaskChangeFormatter;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * The project's tasks from the search index: filterable by free text,
     * status, priority, type and assignee, and paginated.
     */
    public function index(Request $request, Project $project, SearchService $search): Response
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => TaskStatus::tryFrom($request->string('status')->toString())?->value,
            'priority' => TaskPriority::tryFrom($request->string('priority')->toString())?->value,
            'type' => TaskType::tryFrom($request->string('type')->toString())?->value,
            'agent' => $request->integer('agent') ?: null,
        ];

        $results = $search->search(
            SearchCriteria::for(SearchType::Task)
                ->matching($filters['q'])
                ->where('project_id', $project->id)
                ->where('status', $filters['status'])
                ->where('priority', $filters['priority'])
                ->where('task_type', $filters['type'])
                ->where('agent_ids', $filters['agent'])
                ->orderBy('position')
                ->paginate($request->integer('page', 1), 20),
        );

        return Inertia::render('Projects/Tasks/Index', [
            'project' => $project,
            'tasks' => $results->paginate(Task::query()->with('agent:id,name'), $request),
            'filters' => $filters,
            'search_degraded' => $results->degraded,
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'types' => $this->typeOptions(),
            'agents' => $this->agentOptions(),
        ]);
    }

    public function create(Project $project): Response
    {
        return Inertia::render('Projects/Tasks/Create', [
            'project' => $project,
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'types' => $this->typeOptions(),
            'agents' => $this->agentOptions(),
        ]);
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $project->tasks()->create($request->validated());

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('status', 'Feladat létrehozva.');
    }

    public function show(Project $project, Task $task, TaskChangeFormatter $formatter): Response
    {
        return Inertia::render('Projects/Tasks/Show', [
            'project' => $project,
            'task' => $task->load('agent:id,name'),
            'types' => $this->typeOptions(),
            'history' => $formatter->format(
                $task->taskChanges()->with(['user:id,name', 'agent:id,name'])->get()
            ),
            'comments' => $task->comments()
                ->with(['user:id,name', 'agent:id,name'])
                ->get()
                ->map(fn (TaskComment $comment): array => $comment->toDisplayArray()),
            'attachments' => $task->attachments()
                ->orderBy('attachments.id')
                ->get()
                ->map(fn (Attachment $attachment): array => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'source' => $attachment->source,
                    'created_at' => $attachment->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Hand the task to its assigned agent as a prompt run on the task's project,
     * so the agent can carry the work out. The task's details become the prompt.
     */
    public function run(Project $project, Task $task, TaskService $tasks): RedirectResponse
    {
        $task->loadMissing('agent');

        if ($task->agent === null) {
            return back()->with('status', 'A feladathoz nincs agent rendelve, így nem indítható el.');
        }

        $task->setRelation('project', $project);

        $tasks->run($task, request()->user());

        return back()->with(
            'status',
            "Feladat elindítva: {$task->agent->name} megkapta a(z) „{$task->title}” feladatot.",
        );
    }

    public function edit(Project $project, Task $task): Response
    {
        return Inertia::render('Projects/Tasks/Edit', [
            'project' => $project,
            'task' => $task,
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'types' => $this->typeOptions(),
            'agents' => $this->agentOptions(),
        ]);
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('status', 'Feladat frissítve.');
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('status', 'Feladat törölve.');
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
     * Assignable agents for the task form's assignee select.
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
}
