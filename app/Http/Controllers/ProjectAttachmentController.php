<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectAttachmentRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Repositories\Contracts\AttachmentRepository;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
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
     * The project's attachments (uploaded, synced from the attachments folder, or
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
     * Upload a file into the project's attachments folder and record it, so the
     * bytes live in the shared attachments store, outside the project's own tree.
     */
    public function store(StoreProjectAttachmentRequest $request, Project $project): RedirectResponse
    {
        $attachment = $this->attachments->storeUploadedFile($project, $request->file('file'));

        return redirect()
            ->route('projects.attachments.index', $project)
            ->with('status', "Csatolmány feltöltve: {$attachment->filename}.");
    }

    /**
     * The project's attachments as JSON, for the chat attachment picker. Each row
     * carries its absolute `path` so a reference inserted into the prompt points
     * the agent straight at the file on disk.
     */
    public function picker(Project $project): JsonResponse
    {
        $attachments = $project->attachments()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'attachments' => $attachments->map(fn (Attachment $attachment): array => $this->pickerPayload($attachment))->all(),
        ]);
    }

    /**
     * Upload a file from the chat attachment picker and return the stored
     * attachment as JSON, so the modal can list it without a page reload.
     */
    public function pickerUpload(StoreProjectAttachmentRequest $request, Project $project): JsonResponse
    {
        $attachment = $this->attachments->storeUploadedFile($project, $request->file('file'));

        return response()->json([
            'attachment' => $this->pickerPayload($attachment),
        ], 201);
    }

    /**
     * Shape an attachment for the picker. `path` is the absolute filesystem path
     * the agent reads once the reference lands in the prompt.
     *
     * @return array<string, mixed>
     */
    private function pickerPayload(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'filename' => $attachment->filename,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'source' => $attachment->source,
            'relative_path' => $attachment->relative_path,
            'path' => $attachment->absolutePath(),
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
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
     * the row — which also removes its file from the attachments folder, so a
     * later sync does not re-import it.
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
     * Reload the project's attachments folder into the database.
     */
    public function sync(Project $project): RedirectResponse
    {
        $attachments = $this->attachments->syncProject($project);

        return redirect()
            ->route('projects.attachments.index', $project)
            ->with('status', "{$attachments->count()} csatolmány betöltve a lemezről.");
    }
}
