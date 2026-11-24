<?php

use App\Enums\PromptRunStatus;
use App\Models\PromptRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows the running prompt and the queued prompts in order', function () {
    $this->actingAs(User::factory()->create());

    $running = PromptRun::factory()->create([
        'status' => PromptRunStatus::Running,
        'started_at' => now(),
    ]);
    $firstQueued = PromptRun::factory()->create();
    $secondQueued = PromptRun::factory()->create();
    PromptRun::factory()->completed()->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('promptQueue.running', 1)
            ->where('promptQueue.running.0.id', $running->id)
            ->where('promptQueue.running.0.project.name', $running->project->name)
            ->where('promptQueue.queued.0.id', $firstQueued->id)
            ->where('promptQueue.queued.1.id', $secondQueued->id)
            ->where('promptQueue.queued_count', 2)
        );
});

test('the dashboard lists only the first queued prompts but counts all of them', function () {
    $this->actingAs(User::factory()->create());

    PromptRun::factory()->count(12)->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('promptQueue.queued', 10)
            ->where('promptQueue.queued_count', 12)
        );
});
