<?php

namespace App\Mcp\Concerns;

use App\Mcp\Servers\MessageServer;
use App\Models\Agent;
use App\Models\Project;
use App\Repositories\Contracts\PromptRunRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;

trait ResolvesMessagingContext
{
    use FormatsTasks;

    /**
     * Resolve the acting agent and the project of a messages MCP call. A server started
     * for a prompt run always acts as that run's agent in that run's project, so an agent
     * cannot speak for another one; outside a run both come from the call's arguments.
     *
     * @param  array{project?: string|null, agent?: string|null}  $arguments
     * @return array{0: Agent, 1: Project}|string The agent and project, or an error message.
     */
    protected function resolveMessagingContext(array $arguments): array|string
    {
        $promptRunId = (int) getenv(MessageServer::PROMPT_RUN_ENV);

        if ($promptRunId > 0) {
            $run = app(PromptRunRepository::class)->findWithAgentAndProject($promptRunId);

            if ($run?->agent === null || $run->project === null) {
                return 'Ez az agent-futás nem tartozik agenthez és projekthez, ezért nem használhatja az üzeneteket.';
            }

            return [$run->agent, $run->project];
        }

        if (empty($arguments['project']) || empty($arguments['agent'])) {
            return 'Agent-futáson kívül add meg a `project` és az `agent` paramétert.';
        }

        $project = $this->resolveProject($arguments['project']);

        if ($project === null) {
            return "Nincs „{$arguments['project']}” nevű projekt.";
        }

        $agent = $this->resolveAgent($arguments['agent']);

        if ($agent === null) {
            return "Nincs „{$arguments['agent']}” nevű agent. Használd a list-agents eszközt a nevekhez.";
        }

        return [$agent, $project];
    }

    /**
     * The schema of the arguments that name the acting agent and project outside a prompt run.
     *
     * @return array<string, mixed>
     */
    protected function messagingContextSchema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('Csak agent-futáson kívül: a projekt neve (vagy azonosítója). Futás közben a futás projektje.'),
            'agent' => $schema->string()
                ->description('Csak agent-futáson kívül: melyik agent nevében (név, slug vagy azonosító). Futás közben a futás agentje.'),
        ];
    }
}
