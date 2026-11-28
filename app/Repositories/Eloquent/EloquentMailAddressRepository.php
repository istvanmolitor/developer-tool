<?php

namespace App\Repositories\Eloquent;

use App\Models\MailAddress;
use App\Repositories\Contracts\MailAddressRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * @extends EloquentRepository<MailAddress>
 */
class EloquentMailAddressRepository extends EloquentRepository implements MailAddressRepository
{
    protected function model(): string
    {
        return MailAddress::class;
    }

    public function paginateForIndex(string $query, string $sort, string $direction, int $perPage): LengthAwarePaginator
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $this->newQuery()
            ->when($query !== '', function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $where) use ($query): void {
                    $where->where('mail', 'like', "%{$query}%")
                        ->orWhere('name', 'like', "%{$query}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function remember(string $mail, ?string $name = null): ?MailAddress
    {
        $mail = Str::lower(trim($mail));

        if ($mail === '') {
            return null;
        }

        $name = is_string($name) && trim($name) !== '' ? trim($name) : null;

        $address = $this->newQuery()->firstOrCreate(
            ['mail' => $mail],
            ['name' => $name, 'is_spam' => false],
        );

        // Fill in a name once, without overwriting one already stored.
        if (! $address->wasRecentlyCreated && $address->name === null && $name !== null) {
            $address->forceFill(['name' => $name])->save();
        }

        return $address;
    }
}
