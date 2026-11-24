<?php

namespace App\Services;

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class ClaudePromptRunner
{
    /**
     * Create a pending prompt run for the given project and queue it for
     * execution. Returns the freshly created record.
     */
    public function queue(Project $project, string $prompt, ?User $user = null, ?Agent $agent = null): PromptRun
    {
        $run = PromptRun::create([
            'project_id' => $project->id,
            'user_id' => $user?->id,
            'agent_id' => $agent?->id,
            'prompt' => $prompt,
            'status' => PromptRunStatus::Pending,
        ]);

        RunClaudePrompt::dispatch($run);

        return $run;
    }

    /**
     * Run the given agent against a project, optionally with a task prompt.
     *
     * The agent's definition (system prompt, model, tools) is applied to the
     * claude run through {@see command()}. When no prompt is given, the agent's
     * description — or a generic kickoff — stands in as the task, so the agent's
     * own system prompt drives the work.
     */
    public function queueAgent(Project $project, Agent $agent, ?string $prompt = null, ?User $user = null): PromptRun
    {
        return $this->queue($project, $this->agentPrompt($agent, $prompt), $user, $agent);
    }

    /**
     * Run the prompt of the given PromptRun through `claude -p` inside the
     * project's directory, streaming the result back onto the record.
     */
    public function run(PromptRun $run): PromptRun
    {
        $project = $run->project;

        if ($project === null) {
            return $this->fail($run, 'A futtatáshoz tartozó projekt már nem létezik.');
        }

        $run->forceFill([
            'status' => PromptRunStatus::Running,
            'started_at' => now(),
        ])->save();

        try {
            $result = Process::path($project->path)
                ->env($this->environment())
                ->timeout($this->timeout())
                ->run($this->command($run->prompt, $run->agent));
        } catch (Throwable $exception) {
            return $this->fail($run, $exception->getMessage());
        }

        $parsed = $this->parseResult($result->output());

        $run->forceFill([
            'status' => $result->successful() ? PromptRunStatus::Completed : PromptRunStatus::Failed,
            'exit_code' => $result->exitCode(),
            'output' => $parsed['output'],
            'error_output' => $result->errorOutput(),
            'cost_usd' => $parsed['cost_usd'],
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    /**
     * Pull the human-readable text and the USD cost (the "credits" a run burns)
     * out of claude's `--output-format json` stdout. Falls back to the raw
     * output and a null cost when the payload isn't the expected result object.
     *
     * @return array{output: ?string, cost_usd: ?float}
     */
    private function parseResult(string $stdout): array
    {
        $decoded = json_decode(trim($stdout), true);

        if (! is_array($decoded)) {
            return [
                'output' => $stdout === '' ? null : $stdout,
                'cost_usd' => null,
            ];
        }

        $text = $decoded['result'] ?? null;
        $cost = $decoded['total_cost_usd'] ?? null;

        return [
            'output' => is_string($text) ? $text : $stdout,
            'cost_usd' => is_numeric($cost) ? (float) $cost : null,
        ];
    }

    /**
     * @return list<string>
     */
    public function command(string $prompt, ?Agent $agent = null): array
    {
        // JSON output carries the run's usage/cost (total_cost_usd) alongside
        // the text result, so the "credits" a run burns can be persisted.
        $command = [config('developer-tool.claude.binary'), '-p', $prompt, '--output-format', 'json'];

        if ($agent !== null) {
            array_push($command, ...$this->agentOptions($agent));
        }

        if (config('developer-tool.claude.skip_permissions')) {
            $command[] = '--dangerously-skip-permissions';
        }

        return $command;
    }

    /**
     * Resolve the task prompt for an agent run, falling back to the agent's
     * description, then to a generic kickoff, when none is supplied.
     */
    private function agentPrompt(Agent $agent, ?string $prompt): string
    {
        $prompt = trim((string) $prompt);

        if ($prompt !== '') {
            return $prompt;
        }

        $description = trim((string) $agent->description);

        return $description !== '' ? $description : 'Kezdd el a feladatodat a projekten.';
    }

    /**
     * Translate an agent into the `--agents`/`--agent` CLI flags, injecting its
     * definition inline so it can run against any project regardless of what
     * agent files happen to live in that project's directory.
     *
     * @return list<string>
     */
    private function agentOptions(Agent $agent): array
    {
        $slug = Str::slug($agent->name) ?: 'agent-'.$agent->id;

        $definition = array_filter([
            'description' => trim((string) $agent->description) ?: $agent->name,
            'prompt' => trim((string) $agent->system_prompt) ?: $agent->name,
            'model' => $agent->model,
            'tools' => Agent::normalizeTools($agent->tools),
        ], fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        $json = json_encode(
            [$slug => $definition],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return ['--agents', $json, '--agent', $slug];
    }

    private function fail(PromptRun $run, string $message): PromptRun
    {
        $run->forceFill([
            'status' => PromptRunStatus::Failed,
            'error_output' => $message,
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        $home = config('developer-tool.claude.home');

        return $home ? ['HOME' => $home] : [];
    }

    private function timeout(): int
    {
        return (int) config('developer-tool.claude.timeout', 1800);
    }
}
