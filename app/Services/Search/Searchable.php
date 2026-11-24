<?php

namespace App\Services\Search;

use App\Enums\SearchType;
use Illuminate\Database\Eloquent\Builder;

/**
 * A model whose rows are kept in the search index.
 */
interface Searchable
{
    /**
     * The search type the model's documents are listed under.
     */
    public static function searchType(): SearchType;

    /**
     * The key telling the model apart from others of the same search type.
     */
    public static function searchModelKey(): string;

    /**
     * The query loading every indexable row with what the document needs.
     *
     * @return Builder<static>
     */
    public static function searchableQuery(): Builder;

    /**
     * The id of the model's document in the index.
     */
    public function searchDocumentId(): string;

    /**
     * The type-specific fields of the document (title, body, filters).
     *
     * @return array<string, mixed>
     */
    public function toSearchDocument(): array;

    /**
     * The complete document: the type-specific fields plus the identifiers.
     *
     * @return array<string, mixed>
     */
    public function toSearchArray(): array;
}
