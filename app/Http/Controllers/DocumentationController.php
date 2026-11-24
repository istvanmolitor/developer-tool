<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentationRequest;
use App\Http\Requests\UpdateDocumentationRequest;
use App\Models\Documentation;
use App\Models\Project;
use App\Services\DocumentationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentationController extends Controller
{
    public function __construct(private DocumentationService $documentations) {}

    public function index(Project $project): Response
    {
        return Inertia::render('Projects/Documentations/Index', [
            'project' => $project,
            'documentations' => $project->documentations()
                ->orderBy('path')
                ->get(['id', 'path', 'title', 'updated_at']),
            'documentations_path' => $this->documentations->directoryFor($project),
        ]);
    }

    public function create(Project $project): Response
    {
        return Inertia::render('Projects/Documentations/Create', [
            'project' => $project,
        ]);
    }

    public function store(StoreDocumentationRequest $request, Project $project): RedirectResponse
    {
        $documentation = $project->documentations()->create($request->validated());

        return redirect()
            ->route('projects.documentations.show', [$project, $documentation])
            ->with('status', 'Dokumentáció létrehozva.');
    }

    public function show(Project $project, Documentation $documentation): Response
    {
        return Inertia::render('Projects/Documentations/Show', [
            'project' => $project,
            'documentation' => $documentation->append('content_html'),
        ]);
    }

    public function edit(Project $project, Documentation $documentation): Response
    {
        return Inertia::render('Projects/Documentations/Edit', [
            'project' => $project,
            'documentation' => $documentation,
        ]);
    }

    public function update(UpdateDocumentationRequest $request, Project $project, Documentation $documentation): RedirectResponse
    {
        $documentation->update($request->validated());

        return redirect()
            ->route('projects.documentations.show', [$project, $documentation])
            ->with('status', 'Dokumentáció frissítve.');
    }

    public function destroy(Project $project, Documentation $documentation): RedirectResponse
    {
        $documentation->delete();

        return redirect()
            ->route('projects.documentations.index', $project)
            ->with('status', 'Dokumentáció törölve.');
    }

    /**
     * Reload the project's docs/documentations folder into the database.
     */
    public function sync(Project $project): RedirectResponse
    {
        $documentations = $this->documentations->syncProject($project);

        return redirect()
            ->route('projects.documentations.index', $project)
            ->with('status', "{$documentations->count()} dokumentáció betöltve a lemezről.");
    }
}
