<?php

namespace App\Http\Controllers;

use App\Enums\SearchType;
use App\Http\Requests\StoreDocumentationRequest;
use App\Http\Requests\UpdateDocumentationRequest;
use App\Models\Documentation;
use App\Models\Project;
use App\Repositories\Contracts\DocumentationRepository;
use App\Services\DocumentationService;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentationController extends Controller
{
    public function __construct(
        private DocumentationService $documentations,
        private DocumentationRepository $documentationRepository,
    ) {}

    /**
     * The project's documentation from the search index: searchable by title,
     * path and content, and paginated.
     */
    public function index(Request $request, Project $project, SearchService $search): Response
    {
        $filters = ['q' => trim($request->string('q')->toString())];

        $results = $search->search(
            SearchCriteria::for(SearchType::Documentation)
                ->matching($filters['q'])
                ->where('project_id', $project->id)
                ->orderBy('path')
                ->paginate($request->integer('page', 1), 25),
        );

        return Inertia::render('Projects/Documentations/Index', [
            'project' => $project,
            // Hydration builder for the search-result paginator.
            'documentations' => $results->paginate(
                Documentation::query()->select(['id', 'path', 'title', 'updated_at']),
                $request,
            ),
            'filters' => $filters,
            'search_degraded' => $results->degraded,
            'has_documentations' => $project->documentations()->exists(),
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
        $this->documentationRepository->update($documentation, $request->validated());

        return redirect()
            ->route('projects.documentations.show', [$project, $documentation])
            ->with('status', 'Dokumentáció frissítve.');
    }

    public function destroy(Project $project, Documentation $documentation): RedirectResponse
    {
        $this->documentationRepository->delete($documentation);

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

    /**
     * Download the project's full documentation as a single PDF file.
     */
    public function pdf(Project $project): HttpResponse
    {
        $documentations = $project->documentations()
            ->orderBy('path')
            ->get()
            ->each->append('content_html');

        $pdf = Pdf::loadView('documentations.pdf', [
            'project' => $project,
            'documentations' => $documentations,
            'generatedAt' => now(),
        ]);

        $fileName = Str::slug($project->name).'-dokumentacio.pdf';

        return $pdf->download($fileName);
    }
}
