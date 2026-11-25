<?php

namespace App\Services;

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Mcp\Servers\MailServer;
use App\Mcp\Servers\MessageServer;
use App\Mcp\Servers\TaskServer;
use App\Models\Agent;
use App\Models\AgentContext;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Throwable;

class ClaudePromptRunner
{
    /**
     * The `mail` MCP server's reply tool as Claude Code names it.
     */
    private const REPLY_EMAIL_TOOL = 'mcp__mail__reply-email';

    /**
     * The `messages` MCP server's tools as Claude Code names them.
     */
    private const MESSAGE_TOOLS = [
        'mcp__messages__list-agents',
        'mcp__messages__send-message',
        'mcp__messages__list-messages',
    ];

    /**
     * The `tasks` MCP server tools every agent run gets, whatever its own tools
     * list: it can pick up its next task, move a task along and comment on it.
     * Richer task tools (list/create/delete) stay opt-in through the tools list.
     */
    private const TASK_TOOLS = [
        'mcp__tasks__next-task',
        'mcp__tasks__update-task',
        'mcp__tasks__add-task-comment',
    ];

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

        $run = PromptRun::create([
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
        ])->save();

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
        ])->save();

        if ($parsed['session_id'] !== null && $parsed['session_id'] !== $context->session_id) {
            $context->forceFill(['session_id' => $parsed['session_id']])->save();
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
     * The `tasks` MCP server is bound to the run ($run), so the task changes the agent
     * makes are logged under its name. Every agent run gets it — along with the next-task,
     * update-task and add-task-comment tools (see {@see TASK_TOOLS}) — so any agent can pick
     * up its next task, move a task along and comment on it; richer task tools (list/create/
     * delete) stay opt-in through the agent's own tools list. The `agents` and `docs` servers
     * are given only to an agent whose tools opt into them (an `mcp__agents__*` / `mcp__docs__*`
     * entry), so the orchestrator can hand out further runs and persist the docs it produced
     * while ordinary agents cannot. When the run handles an incoming e-mail, the agent also
     * gets the `mail` MCP server, bound to that run, so it can answer the message. Every agent
     * run gets the `messages` MCP server, bound to the run, so the agent can message the
     * other agents of the run's project.
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
            $emailRun = $run !== null && $run->incomingEmail()->exists() ? $run : null;

            array_push($command, ...$this->agentOptions($agent, $emailRun !== null, $run !== null, $run !== null));

            $mcpServers = [];

            // Every agent run gets the tasks server (bound to the run); an agent whose
            // tools opt into further task tools gets it even outside a run.
            if ($run !== null || $this->usesTaskTools($agent)) {
                $mcpServers['tasks'] = $this->taskMcpServer($run);
            }

            if ($this->usesServer($agent, 'agents')) {
                $mcpServers['agents'] = $this->localMcpServer('agents');
            }

            if ($this->usesServer($agent, 'docs')) {
                $mcpServers['docs'] = $this->localMcpServer('docs');
            }

            if ($this->usesServer($agent, 'attachments')) {
                $mcpServers['attachments'] = $this->localMcpServer('attachments');
            }

            if ($emailRun !== null) {
                $mcpServers['mail'] = $this->mailMcpServer($emailRun);
            }

            if ($run !== null) {
                $mcpServers['messages'] = $this->messageMcpServer($run);
            }

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
     * An agent restricted to a tool list still gets the reply tool when it handles
     * an e-mail, so every agent with a mailbox can answer its mail, the message
     * tools when bound to a run, so every agent can talk to the others of its project,
     * and the shared task tools when bound to a run, so every agent can pick up and
     * progress its tasks.
     *
     * @return list<string>
     */
    private function agentOptions(Agent $agent, bool $canReplyToEmail = false, bool $canMessageAgents = false, bool $canUseTaskTools = false): array
    {
        $slug = $agent->slug ?: 'agent-'.$agent->id;
        $tools = Agent::normalizeTools($agent->tools);

        if ($tools !== null) {
            $extraTools = [
                ...($canUseTaskTools ? self::TASK_TOOLS : []),
                ...($canReplyToEmail ? [self::REPLY_EMAIL_TOOL] : []),
                ...($canMessageAgents ? self::MESSAGE_TOOLS : []),
            ];

            $tools = array_values(array_unique([...$tools, ...$extraTools]));
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

    /**
     * Whether the agent is allowed any tool of this application's `tasks` MCP server.
     */
    private function usesTaskTools(Agent $agent): bool
    {
        return $this->usesServer($agent, 'tasks');
    }

    /**
     * Whether the agent is allowed any tool of the named local MCP server, i.e.
     * its tools list carries an `mcp__<server>__*` entry.
     */
    private function usesServer(Agent $agent, string $server): bool
    {
        $prefix = "mcp__{$server}__";

        foreach (Agent::normalizeTools($agent->tools) ?? [] as $tool) {
            if (str_starts_with($tool, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `tasks` MCP server definition for `--mcp-config`. Runs happen inside the
     * project's own directory, which has no `.mcp.json` pointing back here, so the
     * server is started through this application's artisan by absolute path. When
     * bound to a run, the run's id goes into the server's environment so the task
     * changes it makes are attributed to the run's agent.
     *
     * @return array{command: string, args: list<string>, env?: array<string, string>}
     */
    private function taskMcpServer(?PromptRun $run): array
    {
        $server = [
            'command' => 'php',
            'args' => [base_path('artisan'), 'mcp:start', 'tasks'],
        ];

        if ($run !== null) {
            $server['env'] = [TaskServer::PROMPT_RUN_ENV => (string) $run->id];
        }

        return $server;
    }

    /**
     * A stateless local MCP server definition for `--mcp-config`. Runs happen
     * inside the project's own directory, which has no `.mcp.json` pointing back
     * here, so the server is started through this application's artisan by
     * absolute path. Used for servers that need no per-run binding (`agents`,
     * `docs`).
     *
     * @return array{command: string, args: list<string>}
     */
    private function localMcpServer(string $server): array
    {
        return [
            'command' => 'php',
            'args' => [base_path('artisan'), 'mcp:start', $server],
        ];
    }

    /**
     * The `mail` MCP server definition for `--mcp-config`, bound to the run through
     * its environment so the agent can only answer the message handed to this run.
     *
     * @return array{command: string, args: list<string>, env: array<string, string>}
     */
    private function mailMcpServer(PromptRun $run): array
    {
        return [
            'command' => 'php',
            'args' => [base_path('artisan'), 'mcp:start', 'mail'],
            'env' => [MailServer::PROMPT_RUN_ENV => (string) $run->id],
        ];
    }

    /**
     * The `messages` MCP server definition for `--mcp-config`, bound to the run through
     * its environment so the agent sends and reads messages as itself in the run's project.
     *
     * @return array{command: string, args: list<string>, env: array<string, string>}
     */
    private function messageMcpServer(PromptRun $run): array
    {
        return [
            'command' => 'php',
            'args' => [base_path('artisan'), 'mcp:start', 'messages'],
            'env' => [MessageServer::PROMPT_RUN_ENV => (string) $run->id],
        ];
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
