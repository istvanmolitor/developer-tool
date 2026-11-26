<?php

namespace App\Repositories\Eloquent;

use App\Models\Attachment;
use App\Repositories\Contracts\AttachmentRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<Attachment>
 */
class EloquentAttachmentRepository extends EloquentRepository implements AttachmentRepository
{
    protected function model(): string
    {
        return Attachment::class;
    }

    public function byIds(array $ids): Collection
    {
        return $this->newQuery()->whereKey($ids)->get();
    }
}
