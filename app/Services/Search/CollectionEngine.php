<?php

namespace App\Services\Search;

use App\Enums\SearchType;
use Illuminate\Support\Str;

/**
 * Answers searches straight from the database: builds every candidate
 * document in PHP and filters, matches and sorts them in memory. Used by the
 * "collection" driver (tests, small setups) and as the fallback while
 * Elasticsearch is unreachable — it is correct, but not fast.
 */
class CollectionEngine
{
    /**
     * Document fields the free-text query is matched against.
     *
     * @var list<string>
     */
    private const TEXT_FIELDS = ['title', 'body', 'extra'];

    public function search(SearchCriteria $criteria): SearchResults
    {
        $types = $criteria->types === [] || $criteria->withTypeCounts ? SearchType::cases() : $criteria->types;
        $terms = $this->terms($criteria->query);

        $documents = [];

        foreach ($types as $type) {
            foreach ($type->models() as $modelClass) {
                foreach ($modelClass::searchableQuery()->lazyById(500) as $model) {
                    $document = $model->toSearchArray();

                    if ($this->passesFilters($document, $criteria) && $this->matches($document, $terms)) {
                        $documents[] = $document;
                    }
                }
            }
        }

        $typeCounts = [];

        if ($criteria->withTypeCounts) {
            foreach ($documents as $document) {
                $typeCounts[$document['type']] = ($typeCounts[$document['type']] ?? 0) + 1;
            }

            if ($criteria->types !== []) {
                $allowed = array_map(fn (SearchType $type): string => $type->value, $criteria->types);
                $documents = array_values(array_filter(
                    $documents,
                    fn (array $document): bool => in_array($document['type'], $allowed, true),
                ));
            }
        }

        usort($documents, fn (array $a, array $b): int => $this->compare($a, $b, $criteria));

        $page = array_slice($documents, ($criteria->page - 1) * $criteria->perPage, $criteria->perPage);

        $hits = array_map(fn (array $document): SearchHit => new SearchHit(
            type: SearchType::from($document['type']),
            model: $document['model'],
            modelId: (int) $document['model_id'],
            document: $document,
            titleHighlight: $this->highlight((string) ($document['title'] ?? ''), $terms, null),
            snippet: $this->highlight((string) ($document['body'] ?? $document['extra'] ?? ''), $terms, 160),
        ), $page);

        return new SearchResults(
            hits: $hits,
            total: count($documents),
            page: $criteria->page,
            perPage: $criteria->perPage,
            typeCounts: $typeCounts,
        );
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function passesFilters(array $document, SearchCriteria $criteria): bool
    {
        foreach ($criteria->filters as $condition) {
            $stored = $document[$condition['field']] ?? null;
            $storedValues = array_map($this->normalize(...), is_array($stored) ? $stored : [$stored]);
            $wanted = array_map($this->normalize(...), $condition['values']);

            $holds = array_intersect($storedValues, $wanted) !== [];

            if ($holds === $condition['negate']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every term has to appear (accent- and case-insensitively) in one of the text fields.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string>  $terms
     */
    private function matches(array $document, array $terms): bool
    {
        if ($terms === []) {
            return true;
        }

        $haystack = $this->fold(implode(' ', array_map(
            fn (string $field): string => (string) ($document[$field] ?? ''),
            self::TEXT_FIELDS,
        )));

        foreach ($terms as $term) {
            if (! str_contains($haystack, $term)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compare(array $a, array $b, SearchCriteria $criteria): int
    {
        foreach ($criteria->sorts as $order) {
            $left = $a[$order['field']] ?? null;
            $right = $b[$order['field']] ?? null;

            // Missing values go last, whatever the direction.
            if ($left === null || $right === null) {
                $result = ($left === null) <=> ($right === null);
            } else {
                $result = is_string($left) && is_string($right)
                    ? strcmp($this->fold($left), $this->fold($right))
                    : $left <=> $right;

                if ($order['direction'] === 'desc') {
                    $result = -$result;
                }
            }

            if ($result !== 0) {
                return $result;
            }
        }

        return $b['model_id'] <=> $a['model_id'];
    }

    /**
     * Escape the text and wrap the matched terms in <mark>; long text is cut
     * to a window around the first match.
     *
     * @param  list<string>  $terms
     */
    private function highlight(string $text, array $terms, ?int $length): ?string
    {
        if ($text === '') {
            return null;
        }

        $folded = $this->fold($text);

        if ($length !== null && mb_strlen($text) > $length) {
            $first = null;

            foreach ($terms as $term) {
                $position = mb_strpos($folded, $term);

                if ($position !== false && ($first === null || $position < $first)) {
                    $first = $position;
                }
            }

            $start = max(0, ($first ?? 0) - 40);
            $text = mb_substr($text, $start, $length);
            $folded = mb_substr($folded, $start, $length);
        }

        $ranges = [];

        foreach ($terms as $term) {
            $offset = 0;

            while (($position = mb_strpos($folded, $term, $offset)) !== false) {
                $ranges[$position] = max($ranges[$position] ?? 0, mb_strlen($term));
                $offset = $position + mb_strlen($term);
            }
        }

        ksort($ranges);

        $result = '';
        $cursor = 0;

        foreach ($ranges as $position => $size) {
            if ($position < $cursor) {
                continue;
            }

            $result .= e(mb_substr($text, $cursor, $position - $cursor))
                .'<mark>'.e(mb_substr($text, $position, $size)).'</mark>';
            $cursor = $position + $size;
        }

        return $result.e(mb_substr($text, $cursor));
    }

    /**
     * @return list<string>
     */
    private function terms(?string $query): array
    {
        if ($query === null) {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/u', $this->fold($query)) ?: [],
            fn (string $term): bool => $term !== '',
        ));
    }

    /**
     * Lower-case and strip accents while keeping the string length, so match
     * positions map back onto the original text.
     */
    private function fold(string $text): string
    {
        return mb_strtolower(strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ö' => 'O', 'Ő' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ű' => 'U',
        ]));
    }

    private function normalize(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            default => Str::lower((string) $value),
        };
    }
}
