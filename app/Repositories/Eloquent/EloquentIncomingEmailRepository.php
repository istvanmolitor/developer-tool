<?php

namespace App\Repositories\Eloquent;

use App\Models\IncomingEmail;
use App\Repositories\Contracts\IncomingEmailRepository;

/**
 * @extends EloquentRepository<IncomingEmail>
 */
class EloquentIncomingEmailRepository extends EloquentRepository implements IncomingEmailRepository
{
    protected function model(): string
    {
        return IncomingEmail::class;
    }

    public function lockForUpdate(int|string $id): IncomingEmail
    {
        return $this->newQuery()->lockForUpdate()->findOrFail($id);
    }

    public function findForPromptRun(int|string $id, int|string $promptRunId): ?IncomingEmail
    {
        return $this->newQuery()
            ->with('promptRun')
            ->whereKey($id)
            ->where('prompt_run_id', $promptRunId)
            ->first();
    }
}
