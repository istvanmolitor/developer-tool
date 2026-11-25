<?php

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\Agent;
use App\Models\IncomingEmail;
use App\Models\Project;
use App\Models\PromptRun;
use App\Services\ClaudePromptRunner;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath.'/alpha', 0755, true);

    config([
        'developer-tool.projects_path' => $this->projectsPath,
        'developer-tool.claude.binary' => 'claude',
        'developer-tool.claude.skip_permissions' => true,
    ]);

    $this->alpha = Project::factory()->create(['name' => 'alpha', 'path' => $this->projectsPath.'/alpha']);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('the command includes the skip-permissions flag when enabled', function () {
    $command = app(ClaudePromptRunner::class)->command('Do the thing');

    expect($command)->toBe(['claude', '-p', 'Do the thing', '--output-format', 'json', '--dangerously-skip-permissions']);
});

test('the command omits the skip-permissions flag when disabled', function () {
    config(['developer-tool.claude.skip_permissions' => false]);

    $command = app(ClaudePromptRunner::class)->command('Do the thing');

    expect($command)->toBe(['claude', '-p', 'Do the thing', '--output-format', 'json']);
});

test('the command requests json output so usage can be captured', function () {
    $command = app(ClaudePromptRunner::class)->command('Do the thing');

    $formatIndex = array_search('--output-format', $command, true);
    expect($formatIndex)->not->toBeFalse();
    expect($command[$formatIndex + 1])->toBe('json');
});

test('an agent is injected into the command as --agents/--agent flags', function () {
    $agent = Agent::factory()->create([
        'name' => 'Kód Átnéző',
        'slug' => 'code-reviewer',
        'description' => 'Átnézi a kódot.',
        'system_prompt' => 'Te egy alapos kódátnéző vagy.',
        'model' => 'claude-sonnet-5',
        'tools' => ['Read', 'Grep'],
    ]);

    $command = app(ClaudePromptRunner::class)->command('Nézd át a diffet', $agent);

    expect($command[0])->toBe('claude');
    expect($command[1])->toBe('-p');
    expect($command[2])->toBe('Nézd át a diffet');

    $agentsIndex = array_search('--agents', $command, true);
    expect($agentsIndex)->not->toBeFalse();

    $definition = json_decode($command[$agentsIndex + 1], true);
    $slug = $command[array_search('--agent', $command, true) + 1];

    expect($slug)->toBe('code-reviewer');
    expect($definition[$slug])->toMatchArray([
        'description' => 'Átnézi a kódot.',
        'prompt' => 'Te egy alapos kódátnéző vagy.',
        'model' => 'claude-sonnet-5',
        'tools' => ['Read', 'Grep'],
    ]);
});

test('an agent allowed task tools gets the tasks mcp server', function () {
    $agent = Agent::factory()->create(['tools' => ['Read', 'mcp__tasks__create-task']]);

    $command = app(ClaudePromptRunner::class)->command('Vedd fel a kérést', $agent);

    $configIndex = array_search('--mcp-config', $command, true);
    expect($configIndex)->not->toBeFalse();
    expect(json_decode($command[$configIndex + 1], true)['mcpServers']['tasks'])->toBe([
        'command' => 'php',
        'args' => [base_path('artisan'), 'mcp:start', 'tasks'],
    ]);
});

test('an agent without task tools gets no mcp config', function () {
    $agent = Agent::factory()->create(['tools' => ['Read', 'Grep']]);

    expect(app(ClaudePromptRunner::class)->command('Nézd át', $agent))->not->toContain('--mcp-config');
});

test('an agent allowed agent tools gets the agents mcp server', function () {
    $agent = Agent::factory()->create(['tools' => ['Read', 'mcp__agents__run-agent']]);

    $command = app(ClaudePromptRunner::class)->command('Delegálj', $agent);

    $configIndex = array_search('--mcp-config', $command, true);
    expect($configIndex)->not->toBeFalse();
    expect(json_decode($command[$configIndex + 1], true)['mcpServers']['agents'])->toBe([
        'command' => 'php',
        'args' => [base_path('artisan'), 'mcp:start', 'agents'],
    ]);
});

test('an agent allowed docs tools gets the docs mcp server', function () {
    $agent = Agent::factory()->create(['tools' => ['Read', 'mcp__docs__sync-documentation']]);

    $command = app(ClaudePromptRunner::class)->command('Töltsd be', $agent);

    $configIndex = array_search('--mcp-config', $command, true);
    expect($configIndex)->not->toBeFalse();
    expect(json_decode($command[$configIndex + 1], true)['mcpServers']['docs'])->toBe([
        'command' => 'php',
        'args' => [base_path('artisan'), 'mcp:start', 'docs'],
    ]);
});

test('an agent without agent or docs tools gets neither server', function () {
    $agent = Agent::factory()->create(['tools' => ['Read', 'mcp__tasks__create-task']]);

    $command = app(ClaudePromptRunner::class)->command('Dolgozz', $agent);

    $servers = json_decode($command[array_search('--mcp-config', $command, true) + 1], true)['mcpServers'];
    expect($servers)->not->toHaveKey('agents');
    expect($servers)->not->toHaveKey('docs');
});

test('a run handling an e-mail gives the agent the mail mcp server bound to the run', function () {
    Process::fake();
    $agent = Agent::factory()->create(['tools' => ['Read', 'mcp__tasks__create-task']]);
    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id, 'agent_id' => $agent->id])->create();
    IncomingEmail::factory()->create(['prompt_run_id' => $run->id]);

    app(ClaudePromptRunner::class)->run($run);

    Process::assertRan(function ($process) use ($run) {
        $command = $process->command;
        $mcpConfig = json_decode($command[array_search('--mcp-config', $command, true) + 1], true);
        $agents = json_decode($command[array_search('--agents', $command, true) + 1], true);

        return array_keys($mcpConfig['mcpServers']) === ['tasks', 'mail', 'messages']
            && $mcpConfig['mcpServers']['tasks']['env'] === ['DEVELOPER_TOOL_PROMPT_RUN_ID' => (string) $run->id]
            && $mcpConfig['mcpServers']['mail'] === [
                'command' => 'php',
                'args' => [base_path('artisan'), 'mcp:start', 'mail'],
                'env' => ['DEVELOPER_TOOL_PROMPT_RUN_ID' => (string) $run->id],
            ]
            && reset($agents)['tools'] === [
                'Read',
                'mcp__tasks__create-task',
                'mcp__tasks__next-task',
                'mcp__tasks__update-task',
                'mcp__tasks__add-task-comment',
                'mcp__mail__reply-email',
                'mcp__messages__list-agents',
                'mcp__messages__send-message',
                'mcp__messages__list-messages',
            ];
    });
});

test('a run without an e-mail gets no mail mcp server', function () {
    Process::fake();
    $agent = Agent::factory()->create(['tools' => ['Read']]);
    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id, 'agent_id' => $agent->id])->create();

    app(ClaudePromptRunner::class)->run($run);

    Process::assertRan(function ($process) {
        $command = $process->command;
        $mcpConfig = json_decode($command[array_search('--mcp-config', $command, true) + 1], true);

        return ! array_key_exists('mail', $mcpConfig['mcpServers']);
    });
});

test('an agent run gets the messages mcp server bound to the run', function () {
    Process::fake();
    $agent = Agent::factory()->create(['tools' => ['Read']]);
    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id, 'agent_id' => $agent->id])->create();

    app(ClaudePromptRunner::class)->run($run);

    Process::assertRan(function ($process) use ($run) {
        $command = $process->command;
        $mcpConfig = json_decode($command[array_search('--mcp-config', $command, true) + 1], true);
        $agents = json_decode($command[array_search('--agents', $command, true) + 1], true);

        return $mcpConfig['mcpServers']['messages'] === [
            'command' => 'php',
            'args' => [base_path('artisan'), 'mcp:start', 'messages'],
            'env' => ['DEVELOPER_TOOL_PROMPT_RUN_ID' => (string) $run->id],
        ]
            && in_array('mcp__messages__send-message', reset($agents)['tools'], true);
    });
});

test('every agent run gets the shared task tools and the tasks server bound to the run', function () {
    Process::fake();
    // An agent whose tools list opts into no task tools at all.
    $agent = Agent::factory()->create(['tools' => ['Read', 'Grep']]);
    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id, 'agent_id' => $agent->id])->create();

    app(ClaudePromptRunner::class)->run($run);

    Process::assertRan(function ($process) use ($run) {
        $command = $process->command;
        $mcpConfig = json_decode($command[array_search('--mcp-config', $command, true) + 1], true);
        $agents = json_decode($command[array_search('--agents', $command, true) + 1], true);
        $tools = reset($agents)['tools'];

        return $mcpConfig['mcpServers']['tasks'] === [
            'command' => 'php',
            'args' => [base_path('artisan'), 'mcp:start', 'tasks'],
            'env' => ['DEVELOPER_TOOL_PROMPT_RUN_ID' => (string) $run->id],
        ]
            && in_array('mcp__tasks__next-task', $tools, true)
            && in_array('mcp__tasks__update-task', $tools, true)
            && in_array('mcp__tasks__add-task-comment', $tools, true);
    });
});

test('the command omits agent flags when no agent is given', function () {
    $command = app(ClaudePromptRunner::class)->command('Do the thing');

    expect($command)->not->toContain('--agents');
    expect($command)->not->toContain('--agent');
});

test('queueAgent creates a pending run bound to the agent and dispatches it', function () {
    Queue::fake();

    $agent = Agent::factory()->create(['name' => 'Builder']);

    $run = app(ClaudePromptRunner::class)->queueAgent($this->alpha, $agent, 'Építsd meg');

    expect($run->status)->toBe(PromptRunStatus::Pending);
    expect($run->agentContext->agent_id)->toBe($agent->id);
    expect($run->agentContext->project_id)->toBe($this->alpha->id);
    expect($run->prompt)->toBe('Építsd meg');

    Queue::assertPushed(RunClaudePrompt::class);
});

test('queueAgent falls back to the agent description when no prompt is given', function () {
    Queue::fake();

    $agent = Agent::factory()->create(['description' => 'Takarítja a kódot.']);

    $run = app(ClaudePromptRunner::class)->queueAgent($this->alpha, $agent);

    expect($run->prompt)->toBe('Takarítja a kódot.');
});

test('a successful process marks the run completed with output', function () {
    Process::fake([
        '*' => Process::result(output: 'Done!', exitCode: 0),
    ]);

    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id])->create();

    app(ClaudePromptRunner::class)->run($run);

    expect($run->fresh())
        ->status->toBe(PromptRunStatus::Completed)
        ->exit_code->toBe(0)
        ->output->toContain('Done!');
});

test('a json result is parsed into its text and persisted cost', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode([
                'type' => 'result',
                'subtype' => 'success',
                'is_error' => false,
                'result' => 'A README fájl elkészült.',
                'total_cost_usd' => 0.0123456,
            ]),
            exitCode: 0,
        ),
    ]);

    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id])->create();

    app(ClaudePromptRunner::class)->run($run);

    expect($run->fresh())
        ->status->toBe(PromptRunStatus::Completed)
        ->output->toBe('A README fájl elkészült.')
        ->cost_usd->toEqual('0.012346');
});

test('a non-json result keeps the raw output and leaves the cost null', function () {
    Process::fake([
        '*' => Process::result(output: 'Plain text result', exitCode: 0),
    ]);

    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id])->create();

    app(ClaudePromptRunner::class)->run($run);

    expect($run->fresh())
        ->output->toContain('Plain text result')
        ->cost_usd->toBeNull();
});

test('a failing process marks the run failed with its error output', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1),
    ]);

    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id])->create();

    app(ClaudePromptRunner::class)->run($run);

    expect($run->fresh())
        ->status->toBe(PromptRunStatus::Failed)
        ->exit_code->toBe(1)
        ->error_output->toContain('boom');
});

test('a run for a missing project is marked failed without running a process', function () {
    Process::fake();

    // Deleting the project nulls the run's project_id (null-on-delete), which
    // is how a run can end up orphaned from the project it was queued against.
    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id])->create();
    $this->alpha->delete();
    $run->refresh();

    app(ClaudePromptRunner::class)->run($run);

    expect($run->fresh()->status)->toBe(PromptRunStatus::Failed);
    Process::assertNothingRan();
});

test('a run stores the claude session id on its context', function () {
    Process::fake([
        '*' => Process::result(output: json_encode(['result' => 'Kész.', 'session_id' => 'session-abc']), exitCode: 0),
    ]);
    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id])->create();

    app(ClaudePromptRunner::class)->run($run);

    expect($run->agentContext->fresh()->session_id)->toBe('session-abc');
});

test('a run in a context with a session resumes that session', function () {
    Process::fake();
    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id, 'session_id' => 'session-abc'])->create();

    app(ClaudePromptRunner::class)->run($run);

    Process::assertRan(fn ($process) => array_slice($process->command, 5, 2) === ['--resume', 'session-abc']);
});

test('a run in a new context starts a new session', function () {
    Process::fake();
    $run = PromptRun::factory()->inContext(['project_id' => $this->alpha->id])->create();

    app(ClaudePromptRunner::class)->run($run);

    Process::assertRan(fn ($process) => ! in_array('--resume', $process->command, true));
});

test('queueAgent runs each prompt in a context of its own', function () {
    Queue::fake();
    $agent = Agent::factory()->create();
    $runner = app(ClaudePromptRunner::class);

    $first = $runner->queueAgent($this->alpha, $agent, 'Első feladat');
    $second = $runner->queueAgent($this->alpha, $agent, 'Második feladat');

    expect($first->agent_context_id)->not->toBe($second->agent_context_id);
});
