<?php

namespace App\Services;

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\Agent;
use App\Models\AgentContext;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\User;
use App\Repositories\Contracts\AgentContextRepository;
use App\Repositories\Contracts\PromptRunRepository;
use Illuminate\Support\Facades\Process;
use Throwable;

class ClaudePromptRunner
{
    public function __construct(
        private PromptRunRepository $promptRuns,
        private AgentContextRepository $agentContexts,
    ) {}

    /**
     * Create a pending prompt run for the given project in a new context — a fresh
     * claude session — and queue it for execution. Returns the freshly created record.
     */
    public function queue(Project $project, string $prompt, ?User $user = null, ?Agent $agent = null): PromptRun
    {
        return $this->queueInContext(AgentContext::start($project, $agent, $user), $prompt);
    }

    /**
     * Run the given agent against a project in a new context, optionally with a task prompt.
     *
     * The agent's definition (system prompt, model, tools) is applied to the
     * claude run through {@see command()}. When no prompt is given, the agent's
     * description — or a generic kickoff — stands in as the task, so the agent's
     * own system prompt drives the work.
     */
    public function queueAgent(Project $project, Agent $agent, ?string $prompt = null, ?User $user = null): PromptRun
    {
        return $this->queueInContext(AgentContext::start($project, $agent, $user), $prompt);
    }

    /**
     * Create a pending prompt run in the given context and queue it for execution. The run
     * continues the context's claude session; an agent context falls back to the agent's
     * description when no prompt is given (see {@see queueAgent()}).
     */
    public function queueInContext(AgentContext $context, ?string $prompt = null): PromptRun
    {
        $agent = $context->agent;

        $run = $this->promptRuns->create([
            'agent_context_id' => $context->id,
            'prompt' => $agent !== null ? $this->agentPrompt($agent, $prompt) : (string) $prompt,
            'status' => PromptRunStatus::Pending,
        ]);

        $run->setRelation('agentContext', $context);

        // A caller may link the run to a record in the same transaction (e.g. an incoming
        // e-mail), so the job only starts once that link is committed and visible.
        RunClaudePrompt::dispatch($run)->afterCommit();

        return $run;
    }

    /**
     * Run the prompt of the given PromptRun through `claude -p` inside the
     * project's directory, streaming the result back onto the record.
     */
    public function run(PromptRun $run): PromptRun
    {
        // Read the context fresh: an earlier run of it may have stored the session id since.
        $context = $run->agentContext()->with(['project', 'agent'])->first();
        $project = $context?->project;

        if ($project === null) {
            return $this->fail($run, 'A futtatáshoz tartozó projekt már nem létezik.');
        }

        $run->forceFill([
            'status' => PromptRunStatus::Running,
            'started_at' => now(),
        ]);
        $this->promptRuns->save($run);

        try {
            $result = Process::path($project->path)
                ->env($this->environment())
                ->timeout($this->timeout())
                ->run($this->command($run->prompt, $context->agent, $run, $context->session_id));
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
        ]);
        $this->promptRuns->save($run);

        if ($parsed['session_id'] !== null && $parsed['session_id'] !== $context->session_id) {
            $context->forceFill(['session_id' => $parsed['session_id']]);
            $this->agentContexts->save($context);
        }

        return $run;
    }

    /**
     * Pull the human-readable text, the USD cost (the "credits" a run burns) and
     * the session id out of claude's `--output-format json` stdout. Falls back to
     * the raw output and a null cost and session when the payload isn't the
     * expected result object.
     *
     * @return array{output: ?string, cost_usd: ?float, session_id: ?string}
     */
    private function parseResult(string $stdout): array
    {
        $decoded = json_decode(trim($stdout), true);

        if (! is_array($decoded)) {
            return [
                'output' => $stdout === '' ? null : $stdout,
                'cost_usd' => null,
                'session_id' => null,
            ];
        }

        $text = $decoded['result'] ?? null;
        $cost = $decoded['total_cost_usd'] ?? null;
        $sessionId = $decoded['session_id'] ?? null;

        return [
            'output' => is_string($text) ? $text : $stdout,
            'cost_usd' => is_numeric($cost) ? (float) $cost : null,
            'session_id' => is_string($sessionId) && $sessionId !== '' ? $sessionId : null,
        ];
    }

    /**
     * Build the `claude -p` command line. With a session id the run resumes that
     * claude session, so it continues the conversation of its context.
     *
     * The MCP servers the run gets, and the MCP tools its agent gets beyond its own
     * tools list, are worked out by {@see AgentMcpConfig}.
     *
     * @return list<string>
     */
    public function command(string $prompt, ?Agent $agent = null, ?PromptRun $run = null, ?string $sessionId = null): array
    {
        // JSON output carries the run's usage/cost (total_cost_usd) and session id
        // alongside the text result, so both can be persisted.
        $command = [config('developer-tool.claude.binary'), '-p', $prompt, '--output-format', 'json'];

        if ($sessionId !== null && $sessionId !== '') {
            array_push($command, '--resume', $sessionId);
        }

        if ($agent !== null) {
            $mcp = new AgentMcpConfig($agent, $run);

            array_push($command, ...$this->agentOptions($agent, $mcp->sharedTools()));

            $mcpServers = $mcp->servers();

            if ($mcpServers !== []) {
                array_push($command, '--mcp-config', json_encode(['mcpServers' => $mcpServers], JSON_UNESCAPED_SLASHES));
            }
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
     * An agent restricted to a tool list still gets the shared MCP tools its run
     * is entitled to (see {@see AgentMcpConfig::sharedTools()}).
     *
     * @param  list<string>  $sharedTools
     * @return list<string>
     */
    private function agentOptions(Agent $agent, array $sharedTools = []): array
    {
        $slug = $agent->slug ?: 'agent-'.$agent->id;
        $tools = AgentToolService::normalize($agent->tools);

        if ($tools !== null) {
            $tools = array_values(array_unique([...$tools, ...$sharedTools]));
        }

        $definition = array_filter([
            'description' => trim((string) $agent->description) ?: $agent->name,
            'prompt' => trim((string) $agent->system_prompt) ?: $agent->name,
            'model' => $agent->model,
            'tools' => $tools,
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
        ]);
        $this->promptRuns->save($run);

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
