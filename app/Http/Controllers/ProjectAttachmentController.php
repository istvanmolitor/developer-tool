<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectAttachmentRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Repositories\Contracts\AttachmentRepository;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectAttachmentController extends Controller
{
    public function __construct(
        private AttachmentService $attachments,
        private AttachmentRepository $attachmentRepository,
    ) {}

    /**
     * The project's attachments (uploaded, synced from docs/attachments, or
     * saved from a message), newest first.
     */
    public function index(Project $project): Response
    {
        return Inertia::render('Projects/Attachments/Index', [
            'project' => $project->only(['id', 'name', 'path']),
            'attachments' => $project->attachments()
                ->orderByDesc('created_at')
                ->get(['id', 'filename', 'mime_type', 'size', 'source', 'relative_path', 'created_at']),
            'attachments_path' => $this->attachments->directoryFor($project),
        ]);
    }

    /**
     * Upload a file into the project's docs/attachments folder and record it, so
     * it lives alongside the project's own files on disk.
     */
    public function store(StoreProjectAttachmentRequest $request, Project $project): RedirectResponse
    {
        $attachment = $this->attachments->storeUploadedFile($project, $request->file('file'));

        return redirect()
            ->route('projects.attachments.index', $project)
            ->with('status', "Csatolmány feltöltve: {$attachment->filename}.");
    }

    /**
     * Download a project attachment. Always sent as a download, never rendered
     * inline, so an uploaded HTML or SVG file cannot run in the app's origin.
     */
    public function download(Project $project, Attachment $attachment): StreamedResponse
    {
        return $attachment->toDownloadResponse();
    }

    /**
     * Remove the attachment: detach it from any tasks and messages, then delete
     * the row — which also removes its file from the project's folder, so a later
     * sync does not re-import it.
     */
    public function destroy(Project $project, Attachment $attachment): RedirectResponse
    {
        $attachment->tasks()->detach();
        $attachment->incomingEmails()->detach();
        $this->attachmentRepository->delete($attachment);

        return redirect()
            ->route('projects.attachments.index', $project)
            ->with('status', 'Csatolmány törölve.');
    }

    /**
     * Reload the project's docs/attachments folder into the database.
     */
    public function sync(Project $project): RedirectResponse
    {
        $attachments = $this->attachments->syncProject($project);

        return redirect()
            ->route('projects.attachments.index', $project)
            ->with('status', "{$attachments->count()} csatolmány betöltve a lemezről.");
    }
}
