<?php

namespace App\Mcp\Tools;

use App\Repositories\Contracts\PromptRunRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-prompt-run')]
#[Title('Prompt futtatás lekérdezése')]
#[Description('Egy prompt futtatás állapota (pending, running, completed, failed) és kimenete a run-agent által visszaadott run_id alapján.')]
#[IsReadOnly]
class GetPromptRunTool extends Tool
{
    public function __construct(private PromptRunRepository $promptRuns) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'run_id' => ['required', 'integer'],
        ]);

        $run = $this->promptRuns->findForStatusView($validated['run_id']);

        if ($run === null) {
            return Response::error("Nincs {$validated['run_id']} azonosítójú futtatás.");
        }

        return Response::json([
            'run_id' => $run->id,
            'context_id' => $run->agent_context_id,
            'agent' => $run->agent?->name,
            'project' => $run->project?->name,
            'prompt' => $run->prompt,
            'status' => $run->status->value,
            'exit_code' => $run->exit_code,
            'output' => $run->output,
            'error_output' => $run->error_output,
            'cost_usd' => $run->cost_usd,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'run_id' => $schema->integer()
                ->description('A futtatás azonosítója (a run-agent válaszából).')
                ->required(),
        ];
    }
}
