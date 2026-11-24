<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskChangeFormatter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Project $project): Response
    {
        return Inertia::render('Projects/Tasks/Index', [
            'project' => $project,
            'tasks' => $project->tasks()
                ->with('agent:id,name')
                ->orderBy('position')
                ->orderByDesc('id')
                ->get(),
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'types' => $this->typeOptions(),
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
        ]);
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
