<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskCommentRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class TaskCommentController extends Controller
{
    /**
     * Add a comment to the task as the logged-in user.
     */
    public function store(StoreTaskCommentRequest $request, Project $project, Task $task): RedirectResponse
    {
        $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return back()->with('status', 'Hozzászólás elmentve.');
    }
}
