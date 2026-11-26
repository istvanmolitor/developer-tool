<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentSyncService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Saving an agent now writes its definition file; point the agents directory
    // at a throwaway folder so tests never touch the real `.claude/agents` files.
    $this->agentsPath = sys_get_temp_dir().'/dev-tool-agents-'.uniqid();
    mkdir($this->agentsPath, 0755, true);
    config(['developer-tool.agents_path' => $this->agentsPath]);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->agentsPath));
});

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
    PromptRun::factory()->inContext(['project_id' => $project->id, 'agent_id' => $agent->id])->create(['prompt' => 'Own run']);
    PromptRun::factory()->inContext(['project_id' => $project->id, 'agent_id' => Agent::factory()->create()->id])->create();

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

test('an agent can be created with the tools selected on the form', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('agents.store'), [
            'name' => 'Kódoló',
            'slug' => 'coder',
            'tools' => ['Read', 'Edit', 'mcp__tasks__next-task'],
            'system_prompt' => 'Kódolsz.',
        ])
        ->assertRedirect(route('agents.index'));

    expect(Agent::where('slug', 'coder')->firstOrFail()->tools)
        ->toBe(['Read', 'Edit', 'mcp__tasks__next-task']);
});

test('the agent form offers the tool catalogue with hungarian names', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['tools' => ['Read']]);

    foreach ([route('agents.create'), route('agents.edit', $agent)] as $url) {
        $this->actingAs($user)
            ->get($url)
            ->assertInertia(fn (Assert $page) => $page
                ->has('tool_groups.0.tools.0.name')
                ->where('tool_groups.0.label', 'Fájlok')
                ->where('tool_groups.0.tools.0.label', 'Fájl olvasása')
            );
    }
});

test('the agent profile shows the tools with their hungarian names', function () {
    $agent = Agent::factory()->create(['tools' => ['Read', 'mcp__tasks__next-task']]);

    $this->actingAs(User::factory()->create())
        ->get(route('agents.profile', $agent))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Agents/Profile')
            ->where('tools.0.name', 'Read')
            ->where('tools.0.label', 'Fájl olvasása')
            ->where('tools.1.name', 'mcp__tasks__next-task')
            ->where('tools.1.label', 'A következő feladatom')
        );
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

test('creating an agent writes its definition file', function () {
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

    $path = $this->agentsPath.'/documentarian.md';
    expect(file_exists($path))->toBeTrue();

    // The written file round-trips through the sync back into the same values.
    config(['developer-tool.agents_path' => $this->agentsPath]);
    Agent::query()->delete();
    app(AgentSyncService::class)->sync();

    expect(Agent::where('slug', 'documentarian')->firstOrFail())
        ->name->toBe('Dokumentáció-író')
        ->description->toBe('Writes docs.')
        ->model->toBe('claude-sonnet-5')
        ->tools->toBe(['Read', 'Grep', 'Edit'])
        ->system_prompt->toBe('You write documentation.');
});

test('updating an agent rewrites its definition file', function () {
    $agent = Agent::factory()->create(['slug' => 'documentarian', 'name' => 'documentarian']);

    $this->actingAs(User::factory()->create())
        ->put(route('agents.update', $agent), [
            'name' => 'Dokumentáció-író',
            'slug' => 'documentarian',
            'description' => 'Updated.',
            'model' => 'claude-opus-4-8',
            'tools' => 'Read, Write',
            'system_prompt' => 'Updated prompt.',
        ])
        ->assertRedirect(route('agents.profile', $agent));

    $contents = file_get_contents($this->agentsPath.'/documentarian.md');
    expect($contents)->toContain('title: ')
        ->toContain('Updated prompt.')
        ->toContain('Read, Write');
});

test('renaming an agent slug removes the old definition file', function () {
    $agent = Agent::factory()->create(['slug' => 'old-slug', 'name' => 'Régi']);
    app(AgentSyncService::class)->writeFile($agent);
    expect(file_exists($this->agentsPath.'/old-slug.md'))->toBeTrue();

    $this->actingAs(User::factory()->create())
        ->put(route('agents.update', $agent), [
            'name' => 'Régi',
            'slug' => 'new-slug',
            'system_prompt' => 'x',
        ])
        ->assertRedirect();

    expect(file_exists($this->agentsPath.'/old-slug.md'))->toBeFalse()
        ->and(file_exists($this->agentsPath.'/new-slug.md'))->toBeTrue();
});

test('deleting an agent removes its definition file', function () {
    $agent = Agent::factory()->create(['slug' => 'documentarian']);
    app(AgentSyncService::class)->writeFile($agent);
    expect(file_exists($this->agentsPath.'/documentarian.md'))->toBeTrue();

    $this->actingAs(User::factory()->create())
        ->delete(route('agents.destroy', $agent))
        ->assertRedirect(route('agents.index'));

    expect(file_exists($this->agentsPath.'/documentarian.md'))->toBeFalse();
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
