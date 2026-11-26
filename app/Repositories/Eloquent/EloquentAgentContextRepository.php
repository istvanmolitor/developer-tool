<?php

namespace App\Repositories\Eloquent;

use App\Models\Agent;
use App\Models\AgentContext;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\User;
use App\Repositories\Contracts\AgentContextRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<AgentContext>
 */
class EloquentAgentContextRepository extends EloquentRepository implements AgentContextRepository
{
    protected function model(): string
    {
        return AgentContext::class;
    }

    public function findForUser(User $user, int|string $id, array $columns = ['*']): ?AgentContext
    {
        return $this->newQuery()
            ->where('user_id', $user->id)
            ->whereNotNull('project_id')
            ->find($id, $columns);
    }

    public function currentForUser(Project $project, ?Agent $agent, User $user, array $columns = ['*']): ?AgentContext
    {
        return $this->newQuery()
            ->where('project_id', $project->id)
            ->where('agent_id', $agent?->id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->first($columns);
    }

    public function recentForUser(User $user, int $limit): Collection
    {
        return $this->newQuery()
            ->where('user_id', $user->id)
            ->whereHas('project')
            ->whereHas('promptRuns')
            ->with(['project:id,name', 'agent:id,name'])
            ->withMax('promptRuns', 'id')
            ->withMax('promptRuns', 'created_at')
            ->addSelect(['title' => PromptRun::query()
                ->select('prompt')
                ->whereColumn('agent_context_id', 'agent_contexts.id')
                ->orderBy('id')
                ->limit(1),
            ])
            ->orderByDesc('prompt_runs_max_id')
            ->limit($limit)
            ->get();
    }

    public function latestPerProjectForAgentUser(Agent $agent, User $user): Collection
    {
        return $this->newQuery()
            ->whereIn('id', $agent->agentContexts()
                ->selectRaw('max(id)')
                ->where('user_id', $user->id)
                ->whereNotNull('project_id')
                ->groupBy('project_id'))
            ->get(['id', 'project_id', 'session_id', 'created_at']);
    }

    public function findOwnedInProject(User $user, Project $project, int|string $id): AgentContext
    {
        return $this->newQuery()
            ->where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->findOrFail((int) $id);
    }
}
