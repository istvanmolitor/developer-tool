<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
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

test('the agent page offers the projects and lists only its own runs', function () {
    $agent = Agent::factory()->create();
    $project = Project::factory()->create(['name' => 'alpha']);
    PromptRun::factory()->for($project)->create(['agent_id' => $agent->id, 'prompt' => 'Own run']);
    PromptRun::factory()->for($project)->create(['agent_id' => Agent::factory()->create()->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('agents.show', $agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 1)
            ->where('projects.0.name', 'alpha')
            ->has('runs', 1)
            ->where('runs.0.prompt', 'Own run')
            ->where('runs.0.project.name', 'alpha')
        );
});

test('an agent can be created with comma-separated tools', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), [
            'name' => 'Dokumentáció-író',
            'slug' => 'documentarian',
            'description' => 'Writes docs.',
            'model' => 'claude-sonnet-5',
            'tools' => 'Read, Grep, Edit',
            'system_prompt' => 'You write documentation.',
        ])
        ->assertRedirect(route('agents.index'));

    $agent = Agent::where('slug', 'documentarian')->firstOrFail();

    expect($agent->name)->toBe('Dokumentáció-író')
        ->and($agent->tools)->toBe(['Read', 'Grep', 'Edit'])
        ->and($agent->model)->toBe('claude-sonnet-5')
        ->and($agent->system_prompt)->toBe('You write documentation.');
});

test('an agent name must be unique', function () {
    Agent::factory()->create(['name' => 'documentarian']);

    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), [
            'name' => 'documentarian',
            'slug' => 'other',
            'system_prompt' => 'x',
        ])
        ->assertSessionHasErrors('name');
});

test('an agent slug must be unique', function () {
    Agent::factory()->create(['slug' => 'documentarian']);

    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), [
            'name' => 'Dokumentáció-író',
            'slug' => 'documentarian',
            'system_prompt' => 'x',
        ])
        ->assertSessionHasErrors('slug');
});

test('an agent slug must be a lowercase slug', function (string $slug) {
    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), [
            'name' => 'Dokumentáció-író',
            'slug' => $slug,
            'system_prompt' => 'x',
        ])
        ->assertSessionHasErrors('slug');
})->with(['Documentarian', 'dokumentáció-író', 'doc writer', 'docs/writer', '-docs']);

test('an agent requires a name and a system prompt', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), ['name' => '', 'slug' => '', 'system_prompt' => ''])
        ->assertSessionHasErrors(['name', 'slug', 'system_prompt']);
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
            'name' => 'Dokumentáció-író',
            'slug' => $agent->slug,
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

test('guests cannot sync agents', function () {
    $this->post(route('agents.sync'))->assertRedirect(route('login'));
});

test('syncing refreshes agents from the definition files', function () {
    $agentsPath = sys_get_temp_dir().'/dev-tool-agents-'.uniqid();
    mkdir($agentsPath, 0755, true);
    config(['developer-tool.agents_path' => $agentsPath]);

    $existing = Agent::factory()->create(['slug' => 'documentarian', 'model' => 'old-model']);

    file_put_contents($agentsPath.'/documentarian.md', "---\nname: documentarian\ntitle: Dokumentáció-író\nmodel: claude-sonnet-5\n---\nNew prompt.");
    file_put_contents($agentsPath.'/reviewer.md', "---\nname: reviewer\n---\nReview code.");

    try {
        $this->actingAs(User::factory()->create())
            ->post(route('agents.sync'))
            ->assertRedirect(route('agents.index'));
    } finally {
        exec('rm -rf '.escapeshellarg($agentsPath));
    }

    expect(Agent::count())->toBe(2)
        ->and($existing->refresh())
        ->name->toBe('Dokumentáció-író')
        ->model->toBe('claude-sonnet-5')
        ->system_prompt->toBe('New prompt.')
        ->and(Agent::where('slug', 'reviewer')->exists())->toBeTrue();
});

test('syncing with a missing agents directory changes nothing', function () {
    config(['developer-tool.agents_path' => '/no/such/directory']);
    Agent::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('agents.sync'))
        ->assertRedirect(route('agents.index'));

    expect(Agent::count())->toBe(1);
});
