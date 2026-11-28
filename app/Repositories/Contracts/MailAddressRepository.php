<?php

namespace App\Repositories\Contracts;

use App\Models\MailAddress;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends Repository<MailAddress>
 */
interface MailAddressRepository extends Repository
{
    /**
     * The addresses for the list: filtered by free text (address or name) and
     * ordered by a whitelisted column, paginated.
     *
     * @return LengthAwarePaginator<int, MailAddress>
     */
    public function paginateForIndex(string $query, string $sort, string $direction, int $perPage): LengthAwarePaginator;

    /**
     * Record an address (lowercased, unique). A name is filled in only when the
     * address has none yet, so a known contact name is never lost.
     */
    public function remember(string $mail, ?string $name = null): ?MailAddress;
}
