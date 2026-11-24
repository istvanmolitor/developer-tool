<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
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

test('the agent profile shows its settings', function () {
    $agent = Agent::factory()->create(['name' => 'documentarian', 'system_prompt' => 'Write docs.']);

    $this->actingAs(User::factory()->create())
        ->get(route('agents.profile', $agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Agents/Profile')
            ->where('agent.name', 'documentarian')
            ->where('agent.system_prompt', 'Write docs.')
        );
});

test('the agent tasks list its open tasks by priority, then oldest first', function () {
    $agent = Agent::factory()->create();
    $project = Project::factory()->create();

    $assign = fn (string $title, TaskPriority $priority, string $createdAt, TaskStatus $status = TaskStatus::Todo) => Task::factory()
        ->for($project)
        ->create([
            'agent_id' => $agent->id,
            'title' => $title,
            'priority' => $priority,
            'status' => $status,
            'created_at' => $createdAt,
        ]);

    $assign('new low', TaskPriority::Low, '2026-01-01');
    $assign('new high', TaskPriority::High, '2026-03-01');
    $assign('medium', TaskPriority::Medium, '2026-01-01');
    $assign('old high', TaskPriority::High, '2026-02-01', TaskStatus::InProgress);
    $assign('done high', TaskPriority::High, '2025-01-01', TaskStatus::Done);
    Task::factory()->for($project)->create(['agent_id' => Agent::factory()->create()->id, 'status' => TaskStatus::Todo]);

    $this->actingAs(User::factory()->create())
        ->get(route('agents.tasks', $agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Agents/Tasks')
            ->has('tasks.data', 4)
            ->where('tasks.data.0.title', 'old high')
            ->where('tasks.data.1.title', 'new high')
            ->where('tasks.data.2.title', 'medium')
            ->where('tasks.data.3.title', 'new low')
            ->where('tasks.data.0.project.name', $project->name)
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
        ->assertRedirect(route('agents.profile', $agent));

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

test('the agent messages list its conversations and the selected thread', function () {
    $agent = Agent::factory()->create();
    $tester = Agent::factory()->create(['name' => 'tester']);
    $reviewer = Agent::factory()->create(['name' => 'reviewer']);
    $alpha = Project::factory()->create(['name' => 'alpha']);
    $beta = Project::factory()->create(['name' => 'beta']);

    AgentMessage::factory()->for($alpha)->create(['sender_agent_id' => $agent->id, 'recipient_agent_id' => $tester->id, 'body' => 'Kész az API.']);
    AgentMessage::factory()->for($alpha)->create(['sender_agent_id' => $tester->id, 'recipient_agent_id' => $agent->id, 'body' => 'Találtam egy hibát.']);
    AgentMessage::factory()->read()->for($beta)->create(['sender_agent_id' => $reviewer->id, 'recipient_agent_id' => $agent->id, 'body' => 'Átnéztem.']);
    AgentMessage::factory()->for($alpha)->create(['sender_agent_id' => $tester->id, 'recipient_agent_id' => $reviewer->id]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('agents.messages', $agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Agents/Messages')
            ->has('conversations', 2)
            ->where('conversations.0.partner.name', 'reviewer')
            ->where('conversations.0.project.name', 'beta')
            ->where('conversations.0.unread_count', 0)
            ->where('conversations.1.partner.name', 'tester')
            ->where('conversations.1.project.name', 'alpha')
            ->where('conversations.1.message_count', 2)
            ->where('conversations.1.unread_count', 1)
            ->where('selected.partner_id', $reviewer->id)
            ->has('messages', 1)
        );

    $this->actingAs($user)
        ->get(route('agents.messages', ['agent' => $agent, 'project' => $alpha->id, 'partner' => $tester->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.partner_id', $tester->id)
            ->has('messages', 2)
            ->where('messages.0.body', 'Kész az API.')
            ->where('messages.0.outgoing', true)
            ->where('messages.1.body', 'Találtam egy hibát.')
            ->where('messages.1.outgoing', false)
            ->where('messages.1.is_read', false)
        );
});
