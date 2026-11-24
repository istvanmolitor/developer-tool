<?php

use App\Enums\PromptRunStatus;
use App\Models\Project;
use App\Models\PromptRun;
use App\Services\ClaudePromptRunner;
use Illuminate\Support\Facades\Process;

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

    expect($command)->toBe(['claude', '-p', 'Do the thing', '--dangerously-skip-permissions']);
});

test('the command omits the skip-permissions flag when disabled', function () {
    config(['developer-tool.claude.skip_permissions' => false]);

    $command = app(ClaudePromptRunner::class)->command('Do the thing');

    expect($command)->toBe(['claude', '-p', 'Do the thing']);
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
