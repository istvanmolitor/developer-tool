<?php

namespace App\Http\Controllers;

use App\Enums\SearchType;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\Project;
use App\Repositories\Contracts\DocumentRepository;
use App\Services\DocumentService;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentService $documents,
        private DocumentRepository $documentRepository,
    ) {}

    /**
     * The project's documents from the search index: searchable by title,
     * path and content, and paginated.
     */
    public function index(Request $request, Project $project, SearchService $search): Response
    {
        $filters = ['q' => trim($request->string('q')->toString())];

        $results = $search->search(
            SearchCriteria::for(SearchType::Document)
                ->matching($filters['q'])
                ->where('project_id', $project->id)
                ->orderBy('path')
                ->paginate($request->integer('page', 1), 25),
        );

        return Inertia::render('Projects/Documents/Index', [
            'project' => $project,
            // Hydration builder for the search-result paginator.
            'documents' => $results->paginate(
                Document::query()->select(['id', 'path', 'title', 'updated_at']),
                $request,
            ),
            'filters' => $filters,
            'search_degraded' => $results->degraded,
            'has_documents' => $project->documents()->exists(),
            'documents_path' => $this->documents->directoryFor($project),
        ]);
    }

    public function create(Project $project): Response
    {
        return Inertia::render('Projects/Documents/Create', [
            'project' => $project,
        ]);
    }

    public function store(StoreDocumentRequest $request, Project $project): RedirectResponse
    {
        $document = $project->documents()->create($request->validated());

        return redirect()
            ->route('projects.documents.show', [$project, $document])
            ->with('status', 'Dokumentum létrehozva.');
    }

    public function show(Project $project, Document $document): Response
    {
        return Inertia::render('Projects/Documents/Show', [
            'project' => $project,
            'document' => $document->append('content_html'),
        ]);
    }

    public function edit(Project $project, Document $document): Response
    {
        return Inertia::render('Projects/Documents/Edit', [
            'project' => $project,
            'document' => $document,
        ]);
    }

    public function update(UpdateDocumentRequest $request, Project $project, Document $document): RedirectResponse
    {
        $this->documentRepository->update($document, $request->validated());

        return redirect()
            ->route('projects.documents.show', [$project, $document])
            ->with('status', 'Dokumentum frissítve.');
    }

    public function destroy(Project $project, Document $document): RedirectResponse
    {
        $this->documentRepository->delete($document);

        return redirect()
            ->route('projects.documents.index', $project)
            ->with('status', 'Dokumentum törölve.');
    }

    /**
     * Reload the project's docs folder into the database.
     */
    public function sync(Project $project): RedirectResponse
    {
        $documents = $this->documents->syncProject($project);

        return redirect()
            ->route('projects.documents.index', $project)
            ->with('status', "{$documents->count()} dokumentum betöltve a lemezről.");
    }

    /**
     * Download every document of the project as a single PDF file.
     */
    public function pdf(Project $project): HttpResponse
    {
        $documents = $project->documents()
            ->orderBy('path')
            ->get()
            ->each->append('content_html');

        $pdf = Pdf::loadView('documents.pdf', [
            'project' => $project,
            'documents' => $documents,
            'generatedAt' => now(),
        ]);

        $fileName = Str::slug($project->name).'-dokumentumok.pdf';

        return $pdf->download($fileName);
    }
}
