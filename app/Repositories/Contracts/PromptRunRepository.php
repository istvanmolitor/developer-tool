<?php

namespace App\Repositories\Contracts;

use App\Models\PromptRun;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for {@see PromptRun} rows.
 *
 * @extends Repository<PromptRun>
 */
interface PromptRunRepository extends Repository
{
    /**
     * How many runs are still waiting in the queue.
     */
    public function pendingCount(): int;

    /**
     * The runs executing right now, oldest first, with their project and agent
     * loaded — the dashboard's "running" list.
     *
     * @return Collection<int, PromptRun>
     */
    public function running(): Collection;

    /**
     * The next runs the worker will pick up, in queue order, with their project
     * and agent loaded — the dashboard's "queued" list.
     *
     * @return Collection<int, PromptRun>
     */
    public function queued(int $limit): Collection;

    /**
     * A single run with its agent and project (including path), for the
     * get-prompt-run MCP tool.
     */
    public function findForStatusView(int|string $id): ?PromptRun;

    /**
     * A single run with its agent and project, for resolving the acting agent
     * and project of a messaging MCP call.
     */
    public function findWithAgentAndProject(int|string $id): ?PromptRun;

    /**
     * A single run with its agent context, for resolving the task-server's
     * current agent and project.
     */
    public function findWithContext(int|string $id): ?PromptRun;
}
