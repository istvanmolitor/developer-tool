<?php

namespace App\Repositories\Eloquent;

use App\Enums\PromptRunStatus;
use App\Models\PromptRun;
use App\Repositories\Contracts\PromptRunRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<PromptRun>
 */
class EloquentPromptRunRepository extends EloquentRepository implements PromptRunRepository
{
    protected function model(): string
    {
        return PromptRun::class;
    }

    public function pendingCount(): int
    {
        return $this->newQuery()->where('status', PromptRunStatus::Pending)->count();
    }

    public function running(): Collection
    {
        return $this->runsWithStatus(PromptRunStatus::Running)
            ->orderBy('started_at')
            ->get();
    }

    public function queued(int $limit): Collection
    {
        return $this->runsWithStatus(PromptRunStatus::Pending)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function findForStatusView(int|string $id): ?PromptRun
    {
        return $this->newQuery()
            ->with(['agent:id,name', 'project:id,name,path'])
            ->find($id);
    }

    public function findWithAgentAndProject(int|string $id): ?PromptRun
    {
        return $this->newQuery()
            ->with(['agent', 'project'])
            ->find($id);
    }

    public function findWithContext(int|string $id): ?PromptRun
    {
        return $this->newQuery()
            ->with('agentContext')
            ->find($id);
    }

    /**
     * @return Builder<PromptRun>
     */
    private function runsWithStatus(PromptRunStatus $status): Builder
    {
        return $this->newQuery()
            ->where('status', $status)
            ->with(['project:id,name', 'agent:id,name'])
            ->select(['id', 'agent_context_id', 'prompt', 'status', 'started_at', 'created_at']);
    }
}
