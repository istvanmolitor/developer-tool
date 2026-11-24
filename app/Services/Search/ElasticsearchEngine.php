<?php

namespace App\Services\Search;

use App\Enums\SearchType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Elasticsearch over its REST API: keeps the index (an alias over a
 * versioned index) up to date and turns search criteria into queries.
 */
class ElasticsearchEngine
{
    /**
     * Fields matched by the free-text query, with their boosts.
     *
     * @var list<string>
     */
    private const TEXT_FIELDS = ['title^3', 'title.hu^2', 'body', 'body.hu', 'extra'];

    /**
     * Fields sorted on a keyword sub-field instead of the analysed text.
     *
     * @var list<string>
     */
    private const TEXT_SORT_FIELDS = ['title', 'path'];

    /**
     * The alias all reads and writes go through.
     */
    public function alias(): string
    {
        return (string) config('search.elasticsearch.index');
    }

    /**
     * Index (or overwrite) the model's document.
     *
     * @param  bool  $waitForRefresh  wait until the document is visible to searches
     */
    public function index(Model&Searchable $model, bool $waitForRefresh = false): void
    {
        $this->send(fn (PendingRequest $http): Response => $http->put(
            $this->alias().'/_doc/'.rawurlencode($model->searchDocumentId()).'?'.http_build_query([
                'require_alias' => 'true',
                'refresh' => $waitForRefresh ? 'wait_for' : 'false',
            ]),
            $model->toSearchArray(),
        ));
    }

    /**
     * Remove a document; a document that is already gone is not an error.
     */
    public function delete(string $documentId, bool $waitForRefresh = false): void
    {
        $this->send(fn (PendingRequest $http): Response => $http->delete(
            $this->alias().'/_doc/'.rawurlencode($documentId).'?'.http_build_query([
                'refresh' => $waitForRefresh ? 'wait_for' : 'false',
            ]),
        ), allowNotFound: true);
    }

    /**
     * Remove every document whose field holds the value.
     */
    public function deleteWhere(string $field, string|int $value): void
    {
        $this->send(fn (PendingRequest $http): Response => $http->post(
            $this->alias().'/_delete_by_query?conflicts=proceed&refresh=true',
            ['query' => ['term' => [$field => $value]]],
        ), allowNotFound: true);
    }

    /**
     * Build a fresh index, fill it with every searchable row, point the alias
     * at it and drop the indices it replaced. Returns the number of documents.
     *
     * @param  (callable(int): void)|null  $progress  called with the running document count
     */
    public function rebuild(?callable $progress = null): int
    {
        $index = $this->alias().'_'.now()->format('YmdHisu');

        $this->send(fn (PendingRequest $http): Response => $http->put($index, [
            'settings' => $this->settings(),
            'mappings' => $this->mappings(),
        ]));

        $count = 0;

        foreach (SearchType::cases() as $type) {
            foreach ($type->models() as $modelClass) {
                $modelClass::searchableQuery()->chunkById(500, function ($models) use ($index, &$count, $progress): void {
                    $this->bulk($index, $models->all());

                    $count += $models->count();

                    if ($progress !== null) {
                        $progress($count);
                    }
                });
            }
        }

        $this->send(fn (PendingRequest $http): Response => $http->get($index.'/_refresh'));

        $previous = $this->indicesBehindAlias();

        $actions = [['add' => ['index' => $index, 'alias' => $this->alias()]]];

        foreach ($previous as $old) {
            $actions[] = ['remove' => ['index' => $old, 'alias' => $this->alias()]];
        }

        $this->send(fn (PendingRequest $http): Response => $http->post('_aliases', ['actions' => $actions]));

        foreach ($previous as $old) {
            $this->send(fn (PendingRequest $http): Response => $http->delete($old), allowNotFound: true);
        }

        return $count;
    }

    public function search(SearchCriteria $criteria): SearchResults
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->post(
            $this->alias().'/_search',
            $this->buildQuery($criteria),
        ))->json();

        $hits = [];

        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $document = $hit['_source'] ?? [];
            $type = SearchType::tryFrom((string) ($document['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            $hits[] = new SearchHit(
                type: $type,
                model: (string) ($document['model'] ?? ''),
                modelId: (int) ($document['model_id'] ?? 0),
                document: $document,
                titleHighlight: $hit['highlight']['title'][0] ?? null,
                snippet: $hit['highlight']['body'][0] ?? $hit['highlight']['extra'][0] ?? null,
                score: isset($hit['_score']) ? (float) $hit['_score'] : null,
            );
        }

        $typeCounts = [];

        foreach ($response['aggregations']['types']['buckets'] ?? [] as $bucket) {
            $typeCounts[(string) $bucket['key']] = (int) $bucket['doc_count'];
        }

        return new SearchResults(
            hits: $hits,
            total: (int) ($response['hits']['total']['value'] ?? 0),
            page: $criteria->page,
            perPage: $criteria->perPage,
            typeCounts: $typeCounts,
        );
    }

    /**
     * The search request body for the criteria.
     *
     * @return array<string, mixed>
     */
    public function buildQuery(SearchCriteria $criteria): array
    {
        $filter = [];
        $mustNot = [];

        foreach ($criteria->filters as $condition) {
            $clause = ['terms' => [$condition['field'] => $condition['values']]];

            if ($condition['negate']) {
                $mustNot[] = $clause;
            } else {
                $filter[] = $clause;
            }
        }

        $typeFilter = $criteria->types === []
            ? null
            : ['terms' => ['type' => array_map(fn (SearchType $type): string => $type->value, $criteria->types)]];

        if ($typeFilter !== null && ! $criteria->withTypeCounts) {
            $filter[] = $typeFilter;
        }

        $must = $criteria->query === null ? [['match_all' => (object) []]] : [[
            'bool' => [
                'should' => [
                    [
                        'multi_match' => [
                            'query' => $criteria->query,
                            'fields' => self::TEXT_FIELDS,
                            'type' => 'best_fields',
                            'operator' => 'and',
                            'fuzziness' => 'AUTO',
                        ],
                    ],
                    [
                        'multi_match' => [
                            'query' => $criteria->query,
                            'fields' => self::TEXT_FIELDS,
                            'type' => 'phrase_prefix',
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ]];

        $sort = $criteria->query === null ? [] : ['_score'];

        foreach ($criteria->sorts as $order) {
            $field = in_array($order['field'], self::TEXT_SORT_FIELDS, true) ? $order['field'].'.sort' : $order['field'];
            $sort[] = [$field => ['order' => $order['direction'], 'missing' => '_last', 'unmapped_type' => 'keyword']];
        }

        $sort[] = ['model_id' => ['order' => 'desc']];

        $body = [
            'query' => ['bool' => array_filter([
                'must' => $must,
                'filter' => $filter,
                'must_not' => $mustNot,
            ])],
            'sort' => $sort,
            'from' => ($criteria->page - 1) * $criteria->perPage,
            'size' => $criteria->perPage,
            'track_total_hits' => true,
            'highlight' => [
                'encoder' => 'html',
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
                'fields' => [
                    'title' => ['number_of_fragments' => 0],
                    'body' => ['fragment_size' => 160, 'number_of_fragments' => 1, 'no_match_size' => 160],
                    'extra' => ['fragment_size' => 160, 'number_of_fragments' => 1],
                ],
            ],
        ];

        if ($criteria->withTypeCounts) {
            $body['aggs'] = ['types' => ['terms' => ['field' => 'type', 'size' => count(SearchType::cases())]]];

            if ($typeFilter !== null) {
                $body['post_filter'] = $typeFilter;
            }
        }

        return $body;
    }

    /**
     * Analysis settings: accent- and case-insensitive text, plus Hungarian stemming.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
            'analysis' => [
                'analyzer' => [
                    'folded' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding'],
                    ],
                ],
                'normalizer' => [
                    'sortable' => [
                        'type' => 'custom',
                        'filter' => ['lowercase', 'asciifolding'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Field mappings shared by every document type.
     *
     * @return array<string, mixed>
     */
    public function mappings(): array
    {
        $text = fn (array $subFields = []): array => [
            'type' => 'text',
            'analyzer' => 'folded',
            'fields' => ['hu' => ['type' => 'text', 'analyzer' => 'hungarian'], ...$subFields],
        ];

        return [
            'dynamic' => false,
            'properties' => [
                'type' => ['type' => 'keyword'],
                'model' => ['type' => 'keyword'],
                'model_id' => ['type' => 'long'],
                'title' => $text(['sort' => ['type' => 'keyword', 'normalizer' => 'sortable', 'ignore_above' => 256]]),
                'body' => $text(),
                'extra' => ['type' => 'text', 'analyzer' => 'folded'],
                'path' => ['type' => 'keyword', 'fields' => ['sort' => ['type' => 'keyword', 'normalizer' => 'sortable']]],
                'project_id' => ['type' => 'long'],
                'agent_ids' => ['type' => 'long'],
                'sender_agent_id' => ['type' => 'long'],
                'recipient_agent_id' => ['type' => 'long'],
                'mailbox_id' => ['type' => 'long'],
                'task_id' => ['type' => 'long'],
                'incoming_email_id' => ['type' => 'long'],
                'status' => ['type' => 'keyword'],
                'priority' => ['type' => 'keyword'],
                'priority_rank' => ['type' => 'integer'],
                'task_type' => ['type' => 'keyword'],
                'direction' => ['type' => 'keyword'],
                'is_read' => ['type' => 'boolean'],
                'position' => ['type' => 'integer'],
                'due_date' => ['type' => 'date'],
                'created_at' => ['type' => 'date'],
                'updated_at' => ['type' => 'date'],
            ],
        ];
    }

    /**
     * Index a batch of models into the given index in one request.
     *
     * @param  list<Model&Searchable>  $models
     */
    private function bulk(string $index, array $models): void
    {
        if ($models === []) {
            return;
        }

        $lines = [];

        foreach ($models as $model) {
            $lines[] = json_encode(['index' => ['_index' => $index, '_id' => $model->searchDocumentId()]]);
            $lines[] = json_encode($model->toSearchArray(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->withBody(implode("\n", $lines)."\n", 'application/x-ndjson')
            ->post('_bulk'));

        if ($response->json('errors') === true) {
            $reason = collect($response->json('items'))->pluck('index.error.reason')->filter()->first();

            throw new SearchUnavailableException('Elasticsearch bulk indexing failed: '.$reason);
        }
    }

    /**
     * The concrete indices the alias currently points at.
     *
     * @return list<string>
     */
    private function indicesBehindAlias(): array
    {
        $response = $this->send(
            fn (PendingRequest $http): Response => $http->get('_alias/'.$this->alias()),
            allowNotFound: true,
        );

        if ($response->notFound()) {
            // A plain (non-alias) index with the alias' name blocks the alias.
            $this->send(fn (PendingRequest $http): Response => $http->delete($this->alias()), allowNotFound: true);

            return [];
        }

        return array_keys($response->json() ?? []);
    }

    /**
     * Run a request against the cluster, turning connection and HTTP errors
     * into a SearchUnavailableException.
     *
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(callable $request, bool $allowNotFound = false): Response
    {
        try {
            $response = $request($this->http());
        } catch (ConnectionException $exception) {
            throw new SearchUnavailableException('Az Elasticsearch nem érhető el: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->failed() && ! ($allowNotFound && $response->notFound())) {
            $reason = $response->json('error.reason') ?? $response->json('error.type') ?? $response->body();

            throw new SearchUnavailableException("Elasticsearch hiba ({$response->status()}): {$reason}");
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        $config = config('search.elasticsearch');

        $http = Http::baseUrl(rtrim((string) $config['host'], '/'))
            ->acceptJson()
            ->asJson()
            ->timeout($config['timeout'])
            ->connectTimeout($config['connect_timeout']);

        if (filled($config['username'])) {
            $http->withBasicAuth((string) $config['username'], (string) $config['password']);
        }

        return $http;
    }
}
