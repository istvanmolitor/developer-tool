<?php

namespace App\Services;

use App\Mcp\Servers\MailServer;
use App\Mcp\Servers\MessageServer;
use App\Mcp\Servers\TaskServer;
use App\Models\Agent;
use App\Models\PromptRun;

/**
 * Works out which of this application's MCP servers an agent run gets — the
 * `--mcp-config` payload — and which MCP tools it gets on top of its own tools
 * list.
 *
 * Runs happen inside the project's own directory, which has no `.mcp.json`
 * pointing back here, so every server is started through this application's
 * artisan by absolute path. A server bound to a run carries the run's id in its
 * environment, so whatever the agent does through it is attributed to the run.
 *
 * @see ClaudePromptRunner::command()
 */
final class AgentMcpConfig
{
    /**
     * The `mail` MCP server's reply tool as Claude Code names it.
     */
    public const REPLY_EMAIL_TOOL = 'mcp__mail__reply-email';

    /**
     * The `mail` MCP server's send tool as Claude Code names it.
     */
    public const SEND_EMAIL_TOOL = 'mcp__mail__send-email';

    /**
     * The servers a run can get, each given only to an agent whose tools opt into
     * it (an `mcp__<server>__*` entry) — the same rule for every server, so the
     * agent's tools list alone decides what it may reach.
     *
     * @var list<string>
     */
    private const OPT_IN_SERVERS = ['tasks', 'agents', 'messages', 'docs', 'attachments', 'projects'];

    /**
     * The servers that are bound to the run they are started for, and the
     * environment variable carrying the run's id, so what the agent does through
     * them is attributed to the run's agent and project.
     *
     * @var array<string, string>
     */
    private const RUN_BOUND_SERVERS = [
        'tasks' => TaskServer::PROMPT_RUN_ENV,
        'messages' => MessageServer::PROMPT_RUN_ENV,
    ];

    /**
     * Whether the agent has a mailbox of its own, once resolved.
     */
    private ?bool $hasMailbox = null;

    public function __construct(
        private readonly Agent $agent,
        private readonly ?PromptRun $run = null,
    ) {}

    /**
     * The `mcpServers` map for `--mcp-config`, empty when the agent gets none.
     *
     * Every server is opt-in: the agent's tools list has to carry an
     * `mcp__<server>__*` entry for it (see {@see OPT_IN_SERVERS}). The `tasks` and
     * `messages` servers are additionally bound to the run, so the task changes and
     * the messages the agent makes are attributed to it. The one server the tools
     * list does not decide is `mail`: the agent's mailboxes do. An agent a mailbox
     * is assigned to gets it (bound to the run), because that assignment is what
     * says it answers that mailbox's mail — see {@see hasMailbox()}.
     *
     * @return array<string, array{command: string, args: list<string>, env?: array<string, string>}>
     */
    public function servers(): array
    {
        $servers = [];

        foreach (self::OPT_IN_SERVERS as $server) {
            if ($this->usesServer($server)) {
                $servers[$server] = $this->server($server, $this->runEnv($server));
            }
        }

        if ($this->hasMailbox()) {
            $servers['mail'] = $this->server('mail', [MailServer::PROMPT_RUN_ENV => (string) $this->run->id]);
        }

        return $servers;
    }

    /**
     * The environment binding the named server to this config's run, for the
     * servers that are run-bound (see {@see RUN_BOUND_SERVERS}).
     *
     * @return array<string, string>|null
     */
    private function runEnv(string $server): ?array
    {
        $variable = self::RUN_BOUND_SERVERS[$server] ?? null;

        if ($variable === null || $this->run === null) {
            return null;
        }

        return [$variable => (string) $this->run->id];
    }

    /**
     * The MCP tools an agent restricted to a tools list gets beyond that list: the
     * reply and send tools, when a mailbox is assigned to the agent, so it can
     * answer that mailbox's mail and send new mail from it. Everything else an
     * agent may reach comes from its own tools list.
     *
     * @return list<string>
     */
    public function sharedTools(): array
    {
        return $this->hasMailbox() ? [self::REPLY_EMAIL_TOOL, self::SEND_EMAIL_TOOL] : [];
    }

    /**
     * Whether a mailbox is assigned to this agent, and the run it could answer
     * that mailbox's mail in exists.
     *
     * Outgoing mail follows the mailbox assignment, not the agent's tools list:
     * the mailbox names the agent that handles its messages, so that assignment
     * is what grants the `mail` server and the reply tool. The reply itself stays
     * scoped to the message the run was handed — the tool only answers the
     * incoming e-mail bound to the run, once.
     */
    private function hasMailbox(): bool
    {
        return $this->hasMailbox ??= $this->run !== null && $this->agent->mailboxes()->exists();
    }

    /**
     * Whether the agent is allowed any tool of the named local MCP server, i.e.
     * its tools list carries an `mcp__<server>__*` entry. An agent with no tools
     * list is restricted to nothing — it inherits every tool — so it gets every
     * server.
     */
    private function usesServer(string $server): bool
    {
        $tools = AgentToolService::normalize($this->agent->tools);

        if ($tools === null) {
            return true;
        }

        $prefix = "mcp__{$server}__";

        foreach ($tools as $tool) {
            if (str_starts_with($tool, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A local MCP server definition for `--mcp-config`, optionally bound to a run
     * through its environment.
     *
     * @param  array<string, string>|null  $env
     * @return array{command: string, args: list<string>, env?: array<string, string>}
     */
    private function server(string $server, ?array $env = null): array
    {
        return array_filter([
            'command' => 'php',
            'args' => [base_path('artisan'), 'mcp:start', $server],
            'env' => $env,
        ], fn ($value): bool => $value !== null);
    }
}
