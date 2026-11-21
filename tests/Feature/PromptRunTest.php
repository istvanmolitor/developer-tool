<?php

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\PromptRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath.'/alpha', 0755, true);

    config(['developer-tool.projects_path' => $this->projectsPath]);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('submitting a prompt queues a claude run', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.runs.store', 'alpha'), ['prompt' => 'Add a README file.'])
        ->assertRedirect();

    $run = PromptRun::sole();
    expect($run->project)->toBe('alpha')
        ->and($run->prompt)->toBe('Add a README file.')
        ->and($run->status)->toBe(PromptRunStatus::Pending);

    Queue::assertPushed(RunClaudePrompt::class, fn (RunClaudePrompt $job) => $job->promptRun->is($run));
});

test('the prompt is required', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.runs.store', 'alpha'), ['prompt' => ''])
        ->assertSessionHasErrors('prompt');

    Queue::assertNothingPushed();
});

test('a prompt cannot be submitted for an unknown project', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.runs.store', 'ghost'), ['prompt' => 'Hello'])
        ->assertNotFound();
});
