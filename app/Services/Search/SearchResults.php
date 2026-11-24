<?php

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * One page of search hits plus the total number of matches.
 */
class SearchResults
{
    /**
     * @param  list<SearchHit>  $hits
     * @param  array<string, int>  $typeCounts  match count per search type value
     * @param  bool  $degraded  whether the database fallback answered instead of Elasticsearch
     */
    public function __construct(
        public readonly array $hits,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $typeCounts = [],
        public bool $degraded = false,
    ) {}

    /**
     * Load the hits' models through the given query, in the order of the hits.
     * Hits of other models and rows that no longer exist are skipped.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>
     */
    public function load(Builder $query): Collection
    {
        /** @var class-string<TModel&Searchable> $modelClass */
        $modelClass = $query->getModel()::class;
        $modelKey = $modelClass::searchModelKey();

        $ids = [];

        foreach ($this->hits as $hit) {
            if ($hit->model === $modelKey) {
                $ids[] = $hit->modelId;
            }
        }

        $models = $query->whereKey($ids)->get()->keyBy(fn (Model $model): int => (int) $model->getKey());

        return new Collection(array_values(array_filter(array_map(
            fn (int $id): ?Model => $models->get($id),
            $ids,
        ))));
    }

    /**
     * Wrap the loaded page of models into a paginator carrying the request's
     * query string, so the page links keep the active filters.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(Builder $query, Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $this->load($query),
            $this->total,
            $this->perPage,
            $this->page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
