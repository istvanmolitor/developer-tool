<?php

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Model;

/**
 * The application's entry point to search: runs queries on the configured
 * driver and keeps the Elasticsearch index in sync with the models.
 *
 * Elasticsearch outages never break a page or a save: searches fall back to
 * the database (flagged as degraded) and failed index writes are reported,
 * to be repaired by `search:reindex`.
 */
class SearchService
{
    public function __construct(
        private ElasticsearchEngine $elasticsearch,
        private CollectionEngine $collection,
    ) {}

    public function usesElasticsearch(): bool
    {
        return config('search.driver') === 'elasticsearch';
    }

    public function search(SearchCriteria $criteria): SearchResults
    {
        if (! $this->usesElasticsearch()) {
            return $this->collection->search($criteria);
        }

        try {
            return $this->elasticsearch->search($criteria);
        } catch (SearchUnavailableException $exception) {
            report($exception);

            $results = $this->collection->search($criteria);
            $results->degraded = true;

            return $results;
        }
    }

    /**
     * Index the model's current row. The row is reloaded so database defaults
     * and the relations the document needs are part of it.
     */
    public function index(Model&Searchable $model): void
    {
        if (! $this->usesElasticsearch()) {
            return;
        }

        $fresh = $model::searchableQuery()->find($model->getKey());

        if ($fresh === null) {
            return;
        }

        $this->write(fn (bool $waitForRefresh) => $this->elasticsearch->index($fresh, $waitForRefresh));
    }

    public function remove(string $documentId): void
    {
        $this->write(fn (bool $waitForRefresh) => $this->elasticsearch->delete($documentId, $waitForRefresh));
    }

    /**
     * Remove every document whose field holds the value — used when a parent
     * row (project, mailbox) goes and the database cascades its children away.
     */
    public function removeWhere(string $field, string|int $value): void
    {
        $this->write(fn () => $this->elasticsearch->deleteWhere($field, $value));
    }

    /**
     * @param  callable(bool): void  $operation  receives whether to wait for the refresh
     */
    private function write(callable $operation): void
    {
        if (! $this->usesElasticsearch()) {
            return;
        }

        try {
            // Web requests usually redirect to a list next, so the change has
            // to be searchable by then; background work does not wait.
            $operation(! app()->runningInConsole());
        } catch (SearchUnavailableException $exception) {
            report($exception);
        }
    }
}
