<?php

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\Agent;
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

    expect($slug)->toBe('kod-atnezo');
    expect($definition[$slug])->toMatchArray([
        'description' => 'Átnézi a kódot.',
        'prompt' => 'Te egy alapos kódátnéző vagy.',
        'model' => 'claude-sonnet-5',
        'tools' => ['Read', 'Grep'],
    ]);
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
    expect($run->agent_id)->toBe($agent->id);
    expect($run->project_id)->toBe($this->alpha->id);
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

    $run = PromptRun::factory()->create(['project_id' => $this->alpha->id]);

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

    $run = PromptRun::factory()->create(['project_id' => $this->alpha->id]);

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

    $run = PromptRun::factory()->create(['project_id' => $this->alpha->id]);

    app(ClaudePromptRunner::class)->run($run);

    expect($run->fresh())
        ->output->toContain('Plain text result')
        ->cost_usd->toBeNull();
});

test('a failing process marks the run failed with its error output', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1),
    ]);

    $run = PromptRun::factory()->create(['project_id' => $this->alpha->id]);

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
    $run = PromptRun::factory()->create(['project_id' => $this->alpha->id]);
    $this->alpha->delete();
    $run->refresh();

    app(ClaudePromptRunner::class)->run($run);

    expect($run->fresh()->status)->toBe(PromptRunStatus::Failed);
    Process::assertNothingRan();
});
