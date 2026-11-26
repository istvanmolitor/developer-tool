<?php

use App\Enums\TaskStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Setting;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Support\Facades\Queue;

/**
 * Az auto_run (automatikus átadás) viselkedése a TaskService-en át.
 *
 * A lényeg a hurokmentesség: futást csak valódi átadás indít (a feladat egy
 * másik agenthez kerül), a dolgozó agent saját módosítása (státusz, komment)
 * SOHA nem indítja újra a futást. Az auto_run csak KAPU: ha false, nincs futás.
 */
beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath.'/alpha', 0755, true);
    config(['developer-tool.projects_path' => $this->projectsPath]);

    $this->alpha = Project::factory()->create(['name' => 'alpha', 'path' => $this->projectsPath.'/alpha']);
    $this->service = app(TaskService::class);

    Queue::fake();
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('bekapcsolt auto_start_tasks mellett agenttel létrehozott feladat azonnal fut', function () {
    Setting::setFlag('auto_start_tasks', true);
    $agent = Agent::factory()->create();

    $task = $this->service->create($this->alpha, [
        'agent_id' => $agent->id,
        'title' => 'Teendő',
        'status' => TaskStatus::Todo,
    ]);

    expect(PromptRun::count())->toBe(1)
        ->and($task->refresh()->status)->toBe(TaskStatus::InProgress);

    expect(PromptRun::sole()->agentContext->agent_id)->toBe($agent->id);
    Queue::assertPushed(RunClaudePrompt::class, 1);
});

test('bekapcsolt auto_start_tasks mellett agent nélkül nem indít futást', function () {
    Setting::setFlag('auto_start_tasks', true);

    $this->service->create($this->alpha, [
        'agent_id' => null,
        'title' => 'Teendő',
        'status' => TaskStatus::Todo,
    ]);

    expect(PromptRun::count())->toBe(0);
});

test('kikapcsolt auto_start_tasks mellett agenttel sem indít futást', function () {
    Setting::setFlag('auto_start_tasks', false);
    $agent = Agent::factory()->create();

    $this->service->create($this->alpha, [
        'agent_id' => $agent->id,
        'title' => 'Teendő',
        'status' => TaskStatus::Todo,
    ]);

    expect(PromptRun::count())->toBe(0);
});

test('piszkozat bekapcsolt auto_start_tasks mellett sem fut (nem futtatható státusz)', function () {
    Setting::setFlag('auto_start_tasks', true);
    $agent = Agent::factory()->create();

    $this->service->create($this->alpha, [
        'agent_id' => $agent->id,
        'title' => 'Teendő',
        'status' => TaskStatus::Draft,
    ]);

    expect(PromptRun::count())->toBe(0);
});

test('lezárt feladat bekapcsolt auto_start_tasks mellett sem fut (terminális státusz)', function () {
    Setting::setFlag('auto_start_tasks', true);
    $agent = Agent::factory()->create();

    $this->service->create($this->alpha, [
        'agent_id' => $agent->id,
        'title' => 'Teendő',
        'status' => TaskStatus::Closed,
    ]);

    expect(PromptRun::count())->toBe(0);
});

test('getDefaultAutoRun az auto_start_tasks kapcsolót tükrözi', function () {
    Setting::setFlag('auto_start_tasks', true);
    expect($this->service->getDefaultAutoRun())->toBeTrue();

    Setting::setFlag('auto_start_tasks', false);
    expect($this->service->getDefaultAutoRun())->toBeFalse();
});

test('agent átrendelése auto_run mellett pontosan egy futást indít az új agentnek', function () {
    $first = Agent::factory()->create();
    $second = Agent::factory()->create();

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $first->id,
        'status' => TaskStatus::Todo,
        'auto_run' => true,
    ]);

    $this->service->update($task, ['agent_id' => $second->id]);

    // Pontosan egy futás: a run() in_progress-írása NEM indít újabb futást.
    expect(PromptRun::count())->toBe(1)
        ->and(PromptRun::sole()->agentContext->agent_id)->toBe($second->id)
        ->and($task->refresh()->status)->toBe(TaskStatus::InProgress);
});

test('ugyanaz az agent saját feladatán státuszt vált: nem indul új futás (nincs önhurok)', function () {
    $agent = Agent::factory()->create();

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $agent->id,
        'status' => TaskStatus::InProgress,
        'auto_run' => true,
    ]);

    // A dolgozó agent lezárja a saját munkáját és kommentel — az agent_id
    // változatlan, ezért ez NEM átadás, és nem indíthat újra futást.
    $this->service->update($task, [
        'status' => TaskStatus::Done,
        'description' => 'Elkészült.',
    ]);

    expect(PromptRun::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('auto_run puszta bekapcsolása (agent-váltás nélkül) nem indít futást — az auto_run csak kapu', function () {
    $agent = Agent::factory()->create();

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $agent->id,
        'status' => TaskStatus::Todo,
        'auto_run' => false,
    ]);

    // Az auto_run csak engedélyez; futást a (másik) agenthez kerülés indít, nem a
    // kapcsoló felkapcsolása. Itt az agent nem változik, ezért nincs futás.
    $this->service->update($task, ['auto_run' => true]);

    expect(PromptRun::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('auto_run false mellett semmilyen módosítás nem indít futást', function () {
    $first = Agent::factory()->create();
    $second = Agent::factory()->create();

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $first->id,
        'status' => TaskStatus::Todo,
        'auto_run' => false,
    ]);

    // Agent-váltás ÉS státuszváltás egyszerre, de auto_run false → a kapu zárva.
    $this->service->update($task, ['agent_id' => $second->id, 'status' => TaskStatus::InProgress]);

    expect(PromptRun::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('agent átrendelése kikapcsolt auto_run mellett nem indít futást', function () {
    $first = Agent::factory()->create();
    $second = Agent::factory()->create();

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $first->id,
        'status' => TaskStatus::Todo,
        'auto_run' => false,
    ]);

    $this->service->update($task, ['agent_id' => $second->id]);

    expect(PromptRun::count())->toBe(0);
});
