<?php

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Mcp\Servers\AgentServer;
use App\Mcp\Tools\GetPromptRunTool;
use App\Mcp\Tools\RunAgentTool;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->project = Project::factory()->create(['name' => 'alpha']);
    $this->agent = Agent::factory()->create(['name' => 'documentarian', 'description' => 'Writes the docs.']);
});

test('run-agent queues the prompt as the agent on the project', function () {
    AgentServer::tool(RunAgentTool::class, [
        'agent' => 'documentarian',
        'project' => 'alpha',
        'prompt' => 'Document the mailbox feature.',
    ])->assertOk()->assertSee('Prompt sorba állítva.');

    $run = PromptRun::sole();
    expect($run->agent_id)->toBe($this->agent->id)
        ->and($run->project_id)->toBe($this->project->id)
        ->and($run->prompt)->toBe('Document the mailbox feature.')
        ->and($run->status)->toBe(PromptRunStatus::Pending);

    Queue::assertPushed(RunClaudePrompt::class, fn (RunClaudePrompt $job) => $job->promptRun->is($run));
});

test('run-agent falls back to the agent description without a prompt', function () {
    AgentServer::tool(RunAgentTool::class, [
        'agent' => 'documentarian',
        'project' => 'alpha',
    ])->assertOk();

    expect(PromptRun::sole()->prompt)->toBe('Writes the docs.');
});

test('run-agent errors on an unknown agent or project', function (array $arguments) {
    AgentServer::tool(RunAgentTool::class, $arguments)->assertHasErrors();

    expect(PromptRun::count())->toBe(0);
})->with([
    'unknown agent' => [['agent' => 'nincs-ilyen', 'project' => 'alpha']],
    'unknown project' => [['agent' => 'documentarian', 'project' => 'nincs-ilyen']],
]);

test('get-prompt-run returns the run status and output', function () {
    $run = PromptRun::factory()->completed()->for($this->project)->create([
        'agent_id' => $this->agent->id,
        'output' => 'Docs written.',
    ]);

    AgentServer::tool(GetPromptRunTool::class, ['run_id' => $run->id])
        ->assertOk()
        ->assertSee('completed')
        ->assertSee('Docs written.');
});

test('get-prompt-run errors on an unknown run', function () {
    AgentServer::tool(GetPromptRunTool::class, ['run_id' => 999])->assertHasErrors();
});
