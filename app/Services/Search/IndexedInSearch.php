<?php

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Keeps the model's search document in sync with its row: saving indexes it,
 * deleting removes it — both once the surrounding transaction commits.
 *
 * @mixin Model
 */
trait IndexedInSearch
{
    public static function bootIndexedInSearch(): void
    {
        static::saved(function (Model&Searchable $model): void {
            $model->getConnection()->afterCommit(fn () => app(SearchService::class)->index($model));
        });

        static::deleted(function (Model&Searchable $model): void {
            $documentId = $model->searchDocumentId();

            $model->getConnection()->afterCommit(fn () => app(SearchService::class)->remove($documentId));
        });
    }

    public static function searchModelKey(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /**
     * @return Builder<static>
     */
    public static function searchableQuery(): Builder
    {
        return static::query();
    }

    public function searchDocumentId(): string
    {
        return static::searchModelKey().'-'.$this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchArray(): array
    {
        return [
            ...$this->toSearchDocument(),
            'type' => static::searchType()->value,
            'model' => static::searchModelKey(),
            'model_id' => $this->getKey(),
        ];
    }
}
