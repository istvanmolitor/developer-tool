<?php

use App\Models\Agent;
use App\Models\Mailbox;
use App\Models\Project;
use App\Models\PromptRun;
use App\Services\AgentMcpConfig;

/**
 * A run of the given agent, so a config can be built for it.
 */
function runFor(Agent $agent): PromptRun
{
    return PromptRun::factory()
        ->inContext(['project_id' => Project::factory()->create()->id, 'agent_id' => $agent->id])
        ->create();
}

test('an agent with no mcp tools gets no servers and no shared tools', function () {
    $agent = Agent::factory()->create(['tools' => ['Read', 'Grep']]);

    $config = new AgentMcpConfig($agent, runFor($agent));

    expect($config->servers())->toBe([]);
    expect($config->sharedTools())->toBe([]);
});

test('an agent allowed attachment tools gets the attachments server', function () {
    $agent = Agent::factory()->create(['tools' => ['Read', 'mcp__attachments__list-attachments']]);

    expect((new AgentMcpConfig($agent))->servers())->toBe([
        'attachments' => [
            'command' => 'php',
            'args' => [base_path('artisan'), 'mcp:start', 'attachments'],
        ],
    ]);
});

test('every server is opt-in through the agents own tools list', function () {
    $servers = [
        'tasks' => 'mcp__tasks__next-task',
        'agents' => 'mcp__agents__run-agent',
        'messages' => 'mcp__messages__send-message',
        'docs' => 'mcp__docs__sync-documents',
        'attachments' => 'mcp__attachments__list-attachments',
        'projects' => 'mcp__projects__create-project',
    ];

    foreach ($servers as $server => $tool) {
        $agent = Agent::factory()->create(['tools' => ['Read', $tool]]);

        expect(array_keys((new AgentMcpConfig($agent, runFor($agent)))->servers()))->toBe([$server]);
    }
});

test('the tasks and messages servers are bound to the run, the others are not', function () {
    $agent = Agent::factory()->create(['tools' => [
        'mcp__tasks__next-task',
        'mcp__messages__send-message',
        'mcp__docs__sync-documents',
    ]]);

    $run = runFor($agent);
    $servers = (new AgentMcpConfig($agent, $run))->servers();

    expect($servers['tasks']['env'])->toBe(['DEVELOPER_TOOL_PROMPT_RUN_ID' => (string) $run->id]);
    expect($servers['messages']['env'])->toBe(['DEVELOPER_TOOL_PROMPT_RUN_ID' => (string) $run->id]);
    expect($servers['docs'])->not->toHaveKey('env');
});

test('an agent with no tools list at all gets every server', function () {
    $agent = Agent::factory()->create(['tools' => null]);

    expect(array_keys((new AgentMcpConfig($agent, runFor($agent)))->servers()))
        ->toBe(['tasks', 'agents', 'messages', 'docs', 'attachments', 'projects']);
});

test('an agent a mailbox is assigned to gets the mail server and the reply tool whatever its tools list', function () {
    $agent = Agent::factory()->create(['tools' => ['Read']]);
    Mailbox::factory()->create(['agent_id' => $agent->id]);
    $run = runFor($agent);

    $config = new AgentMcpConfig($agent, $run);

    expect(array_keys($config->servers()))->toBe(['mail']);
    expect($config->servers()['mail']['env'])->toBe(['DEVELOPER_TOOL_PROMPT_RUN_ID' => (string) $run->id]);
    expect($config->sharedTools())->toBe([AgentMcpConfig::REPLY_EMAIL_TOOL, AgentMcpConfig::SEND_EMAIL_TOOL]);
});

test('an agent without a mailbox gets no mail server and no reply tool', function () {
    $agent = Agent::factory()->create(['tools' => ['Read']]);

    $config = new AgentMcpConfig($agent, runFor($agent));

    expect($config->servers())->toBe([]);
    expect($config->sharedTools())->toBe([]);
});
