<?php

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\PromptRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath.'/alpha', 0755, true);
    mkdir($this->projectsPath.'/beta', 0755, true);

    config(['developer-tool.projects_path' => $this->projectsPath]);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('guests are redirected from the playground', function () {
    $this->get(route('playground'))->assertRedirect(route('login'));
});

test('the playground lists selectable projects and recent runs', function () {
    PromptRun::factory()->create(['project' => 'alpha']);

    $this->actingAs(User::factory()->create())
        ->get(route('playground'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Playground')
            ->has('projects', 2)
            ->has('runs', 1)
        );
});

test('a prompt can be run against a selected project', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('playground.run'), ['project' => 'beta', 'prompt' => 'List the files here.'])
        ->assertRedirect();

    $run = PromptRun::sole();
    expect($run->project)->toBe('beta')
        ->and($run->status)->toBe(PromptRunStatus::Pending);

    Queue::assertPushed(RunClaudePrompt::class);
});

test('an unknown project is rejected', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('playground.run'), ['project' => 'ghost', 'prompt' => 'Hello there.'])
        ->assertSessionHasErrors('project');

    Queue::assertNothingPushed();
});

test('the prompt is required in the playground', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('playground.run'), ['project' => 'alpha', 'prompt' => ''])
        ->assertSessionHasErrors('prompt');

    Queue::assertNothingPushed();
});
