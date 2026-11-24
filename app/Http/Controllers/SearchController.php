<?php

namespace App\Http\Controllers;

use App\Enums\SearchType;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchResultPresenter;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(
        private SearchService $search,
        private SearchResultPresenter $presenter,
    ) {}

    /**
     * The full results page: every type, filterable by type and paginated.
     */
    public function index(Request $request): Response
    {
        $query = trim($request->string('q')->toString());
        $type = SearchType::tryFrom($request->string('type')->toString());

        $results = null;

        if ($query !== '') {
            $results = $this->search->search(
                SearchCriteria::for(...($type === null ? [] : [$type]))
                    ->matching($query)
                    ->withTypeCounts()
                    ->paginate($request->integer('page', 1), 20),
            );
        }

        return Inertia::render('Search/Index', [
            'filters' => ['q' => $query, 'type' => $type?->value],
            'types' => array_map(
                fn (SearchType $searchType): array => [
                    'value' => $searchType->value,
                    'label' => $searchType->label(),
                    'count' => $results?->typeCounts[$searchType->value] ?? 0,
                ],
                SearchType::cases(),
            ),
            'results' => $results === null ? null : [
                'data' => $this->presenter->present($results),
                'total' => $results->total,
                'current_page' => $results->page,
                'last_page' => max(1, (int) ceil($results->total / $results->perPage)),
            ],
            'search_degraded' => $results !== null && $results->degraded,
        ]);
    }

    /**
     * The header's search-as-you-type suggestions.
     */
    public function suggest(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $results = $this->search->search(SearchCriteria::for()->matching($query)->paginate(1, 8));

        return response()->json([
            'results' => $this->presenter->present($results),
            'total' => $results->total,
        ]);
    }
}
