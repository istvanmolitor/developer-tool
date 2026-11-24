<?php

namespace App\Services\Search;

use App\Enums\SearchType;

/**
 * What to look for in the search index: the free text, the document types,
 * exact-match filters, the order and the page.
 */
class SearchCriteria
{
    public ?string $query = null;

    /** @var list<SearchType> */
    public array $types = [];

    /** @var list<array{field: string, values: list<string|int|bool>, negate: bool}> */
    public array $filters = [];

    /** @var list<array{field: string, direction: 'asc'|'desc'}> */
    public array $sorts = [];

    public int $page = 1;

    public int $perPage = 20;

    /**
     * Count the matches per type, ignoring the type restriction.
     */
    public bool $withTypeCounts = false;

    public static function for(SearchType ...$types): self
    {
        $criteria = new self;
        $criteria->types = array_values($types);

        return $criteria;
    }

    public function matching(?string $query): self
    {
        $query = trim((string) $query);
        $this->query = $query === '' ? null : $query;

        return $this;
    }

    /**
     * Keep documents whose field holds the value (or one of the values).
     * A null, empty string or empty list leaves the criteria unchanged.
     *
     * @param  string|int|bool|list<string|int|bool>|null  $value
     */
    public function where(string $field, string|int|bool|array|null $value): self
    {
        return $this->addFilter($field, $value, false);
    }

    /**
     * Drop documents whose field holds the value (or one of the values).
     *
     * @param  string|int|bool|list<string|int|bool>|null  $value
     */
    public function whereNot(string $field, string|int|bool|array|null $value): self
    {
        return $this->addFilter($field, $value, true);
    }

    /**
     * @param  'asc'|'desc'  $direction
     */
    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->sorts[] = ['field' => $field, 'direction' => $direction];

        return $this;
    }

    public function paginate(int $page, int $perPage = 20): self
    {
        $this->page = max(1, $page);
        $this->perPage = max(1, min(100, $perPage));

        return $this;
    }

    public function withTypeCounts(): self
    {
        $this->withTypeCounts = true;

        return $this;
    }

    /**
     * @param  string|int|bool|list<string|int|bool>|null  $value
     */
    private function addFilter(string $field, string|int|bool|array|null $value, bool $negate): self
    {
        $values = array_values(array_filter(
            is_array($value) ? $value : [$value],
            fn (mixed $item): bool => $item !== null && $item !== '',
        ));

        if ($values !== []) {
            $this->filters[] = ['field' => $field, 'values' => $values, 'negate' => $negate];
        }

        return $this;
    }
}
