<?php

use App\Models\Agent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the agents index', function () {
    $this->get(route('agents.index'))->assertRedirect(route('login'));
});

test('the agents index lists every agent', function () {
    Agent::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('agents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Agents/Index')
            ->has('agents', 3)
        );
});

test('an agent can be viewed', function () {
    $agent = Agent::factory()->create(['name' => 'documentarian']);

    $this->actingAs(User::factory()->create())
        ->get(route('agents.show', $agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Agents/Show')
            ->where('agent.name', 'documentarian')
        );
});

test('an agent can be created with comma-separated tools', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), [
            'name' => 'documentarian',
            'description' => 'Writes docs.',
            'model' => 'claude-sonnet-5',
            'tools' => 'Read, Grep, Edit',
            'system_prompt' => 'You write documentation.',
        ])
        ->assertRedirect(route('agents.index'));

    $agent = Agent::where('name', 'documentarian')->firstOrFail();

    expect($agent->tools)->toBe(['Read', 'Grep', 'Edit'])
        ->and($agent->model)->toBe('claude-sonnet-5')
        ->and($agent->system_prompt)->toBe('You write documentation.');
});

test('an agent name must be unique', function () {
    Agent::factory()->create(['name' => 'documentarian']);

    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), [
            'name' => 'documentarian',
            'system_prompt' => 'x',
        ])
        ->assertSessionHasErrors('name');
});

test('an agent requires a name and a system prompt', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), ['name' => '', 'system_prompt' => ''])
        ->assertSessionHasErrors(['name', 'system_prompt']);
});

test('an agent name cannot contain a slash', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), [
            'name' => 'nested/name',
            'system_prompt' => 'x',
        ])
        ->assertSessionHasErrors('name');
});

test('an agent can be updated', function () {
    $agent = Agent::factory()->create(['name' => 'documentarian']);

    $this->actingAs(User::factory()->create())
        ->put(route('agents.update', $agent), [
            'name' => 'documentarian',
            'description' => 'Updated.',
            'model' => 'claude-opus-4-8',
            'tools' => 'Read, Write',
            'system_prompt' => 'Updated prompt.',
        ])
        ->assertRedirect(route('agents.show', $agent));

    expect($agent->fresh())
        ->description->toBe('Updated.')
        ->model->toBe('claude-opus-4-8')
        ->tools->toBe(['Read', 'Write']);
});

test('an agent can be deleted', function () {
    $agent = Agent::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('agents.destroy', $agent))
        ->assertRedirect(route('agents.index'));

    $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
});
