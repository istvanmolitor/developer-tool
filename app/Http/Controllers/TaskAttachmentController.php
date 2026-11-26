<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\AttachmentRepository;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    public function __construct(
        private AttachmentService $attachments,
        private AttachmentRepository $attachmentRepository,
    ) {}

    /**
     * Upload a file and attach it to the task.
     */
    public function store(StoreTaskAttachmentRequest $request, Project $project, Task $task): RedirectResponse
    {
        $attachment = $this->attachments->storeUploadedFile($project, $request->file('file'));
        $this->attachments->attachToTask($attachment, $task);

        return back()->with('status', "Csatolmány feltöltve: {$attachment->filename}.");
    }

    /**
     * Download a task's attachment. Always sent as a download, never rendered
     * inline, so an uploaded HTML or SVG file cannot run in the app's origin.
     */
    public function download(Project $project, Task $task, Attachment $attachment): StreamedResponse
    {
        return $attachment->toDownloadResponse();
    }

    /**
     * Detach the file from the task; if it is no longer referenced anywhere,
     * delete the file and row too.
     */
    public function destroy(Project $project, Task $task, Attachment $attachment): RedirectResponse
    {
        $task->attachments()->detach($attachment->id);

        if ($attachment->tasks()->count() === 0 && $attachment->incomingEmails()->count() === 0) {
            $this->attachmentRepository->delete($attachment);
        }

        return back()->with('status', 'Csatolmány eltávolítva.');
    }
}
