<?php

namespace App\Repositories\Eloquent;

use App\Models\OutgoingEmail;
use App\Repositories\Contracts\OutgoingEmailRepository;

/**
 * @extends EloquentRepository<OutgoingEmail>
 */
class EloquentOutgoingEmailRepository extends EloquentRepository implements OutgoingEmailRepository
{
    protected function model(): string
    {
        return OutgoingEmail::class;
    }

    public function lockForUpdate(int|string $id): OutgoingEmail
    {
        return $this->newQuery()->lockForUpdate()->findOrFail($id);
    }
}
