<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Repositories\Contracts\TaskChangeRepository;
use App\Services\TaskChangeFormatter;
use Inertia\Inertia;
use Inertia\Response;

class TaskChangeController extends Controller
{
    public function __construct(private TaskChangeRepository $taskChanges) {}

    /**
     * The change log of all of a project's tasks, newest first.
     */
    public function index(Project $project, TaskChangeFormatter $formatter): Response
    {
        $entries = $this->taskChanges->forProjectPaginated($project);

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
