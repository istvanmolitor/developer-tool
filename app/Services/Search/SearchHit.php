<?php

namespace App\Services\Search;

use App\Enums\SearchType;

/**
 * One document found by a search.
 */
readonly class SearchHit
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function __construct(
        public SearchType $type,
        public string $model,
        public int $modelId,
        public array $document,
        public ?string $titleHighlight = null,
        public ?string $snippet = null,
        public ?float $score = null,
    ) {}
}
