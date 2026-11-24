<?php

use App\Models\Agent;
use Database\Seeders\AgentSeeder;

beforeEach(function () {
    $this->agentsPath = sys_get_temp_dir().'/dev-tool-agents-'.uniqid();
    mkdir($this->agentsPath, 0755, true);

    config(['developer-tool.agents_path' => $this->agentsPath]);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->agentsPath));
});

function writeAgent(string $path, string $contents): void
{
    file_put_contents($path, $contents);
}

test('it loads an agent definition from a markdown file', function () {
    writeAgent($this->agentsPath.'/documentarian.md', <<<'MD'
        ---
        name: documentarian
        description: Writes docs from the current code.
        model: claude-sonnet-5
        tools: Read, Grep, Edit, Write
        ---

        You write documentation.
        MD);

    $this->seed(AgentSeeder::class);

    $agent = Agent::where('name', 'documentarian')->firstOrFail();

    expect($agent->description)->toBe('Writes docs from the current code.')
        ->and($agent->model)->toBe('claude-sonnet-5')
        ->and($agent->tools)->toBe(['Read', 'Grep', 'Edit', 'Write'])
        ->and($agent->system_prompt)->toBe('You write documentation.')
        ->and($agent->source_path)->toBe($this->agentsPath.'/documentarian.md');
});

test('it falls back to the filename when name front matter is missing', function () {
    writeAgent($this->agentsPath.'/reviewer.md', "No front matter, just a prompt.");

    $this->seed(AgentSeeder::class);

    expect(Agent::where('name', 'reviewer')->exists())->toBeTrue();
});

test('re-seeding updates existing agents instead of duplicating them', function () {
    writeAgent($this->agentsPath.'/documentarian.md', "---\nname: documentarian\nmodel: claude-sonnet-5\n---\nv1");
    $this->seed(AgentSeeder::class);

    writeAgent($this->agentsPath.'/documentarian.md', "---\nname: documentarian\nmodel: claude-opus-4-8\n---\nv2");
    $this->seed(AgentSeeder::class);

    expect(Agent::where('name', 'documentarian')->count())->toBe(1)
        ->and(Agent::where('name', 'documentarian')->first())
        ->model->toBe('claude-opus-4-8')
        ->system_prompt->toBe('v2');
});

test('a missing agents directory seeds nothing without erroring', function () {
    config(['developer-tool.agents_path' => '/no/such/directory']);

    $this->seed(AgentSeeder::class);

    expect(Agent::count())->toBe(0);
});
