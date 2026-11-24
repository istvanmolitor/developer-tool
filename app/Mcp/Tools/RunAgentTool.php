<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsTasks;
use App\Services\ClaudePromptRunner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('run-agent')]
#[Title('Prompt küldése agentnek')]
#[Description('Sorba állít egy promptot, amelyet a megadott agent a megadott projekten futtat. A futás a háttérben történik; az eredményt a get-prompt-run eszközzel kérdezheted le a visszaadott run_id alapján.')]
class RunAgentTool extends Tool
{
    use FormatsTasks;

    public function handle(Request $request, ClaudePromptRunner $runner): Response
    {
        $validated = $request->validate([
            'agent' => ['required', 'string'],
            'project' => ['required', 'string'],
            'prompt' => ['nullable', 'string', 'min:3', 'max:20000'],
        ]);

        $agent = $this->resolveAgent($validated['agent']);

        if ($agent === null) {
            return Response::error("Nincs „{$validated['agent']}” nevű agent. Használd a list-agents eszközt a nevekhez.");
        }

        $project = $this->resolveProject($validated['project']);

        if ($project === null) {
            return Response::error("Nincs „{$validated['project']}” nevű projekt. Használd a list-projects eszközt a nevekhez.");
        }

        $run = $runner->queueAgent($project, $agent, $validated['prompt'] ?? null);

        return Response::json([
            'message' => 'Prompt sorba állítva.',
            'run_id' => $run->id,
            'agent' => $agent->name,
            'project' => $project->name,
            'prompt' => $run->prompt,
            'status' => $run->status->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'agent' => $schema->string()
                ->description('Az agent neve (vagy azonosítója). A nevekhez használd a list-agents eszközt.')
                ->required(),
            'project' => $schema->string()
                ->description('A projekt neve (vagy azonosítója), amelyen az agent fut. A nevekhez használd a list-projects eszközt.')
                ->required(),
            'prompt' => $schema->string()
                ->description('Opcionális: a feladat az agentnek. Üresen hagyva az agent a leírása alapján indul.'),
        ];
    }
}
