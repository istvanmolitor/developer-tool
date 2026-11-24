<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TaskChange;
use App\Services\TaskChangeFormatter;
use Inertia\Inertia;
use Inertia\Response;

class TaskChangeController extends Controller
{
    /**
     * The change log of all of a project's tasks, newest first.
     */
    public function index(Project $project, TaskChangeFormatter $formatter): Response
    {
        $entries = TaskChange::query()
            ->whereHas('task', fn ($query) => $query->whereBelongsTo($project))
            ->with(['task:id,title', 'user:id,name', 'agent:id,name'])
            ->latest('id')
            ->paginate(50);

        return Inertia::render('Projects/TaskChanges/Index', [
            'project' => $project,
            'entries' => $formatter->format($entries->items()),
            'pagination' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
                'prev_page_url' => $entries->previousPageUrl(),
                'next_page_url' => $entries->nextPageUrl(),
            ],
        ]);
    }
}
