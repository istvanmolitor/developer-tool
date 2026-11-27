<?php

use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Services\TaskService;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('settings page is displayed with the current flags', function () {
    Setting::setFlag('auto_start_tasks', true);

    $this->get(route('app-settings.edit'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('AppSettings/Index')
                ->where('settings.auto_start_tasks', true)
        );
});

test('the auto start tasks setting can be saved', function () {
    $this->patch(route('app-settings.update'), ['auto_start_tasks' => true])
        ->assertRedirect(route('app-settings.edit'));

    expect(Setting::flag('auto_start_tasks'))->toBeTrue();
});

test('an unchecked checkbox turns the setting off', function () {
    Setting::setFlag('auto_start_tasks', true);

    $this->patch(route('app-settings.update'), [])
        ->assertRedirect(route('app-settings.edit'));

    expect(Setting::flag('auto_start_tasks'))->toBeFalse();
});

test('the Setting model stores and reads key-value pairs', function () {
    Setting::set('some_key', 'some_value');

    expect(Setting::get('some_key'))->toBe('some_value')
        ->and(Setting::get('missing', 'fallback'))->toBe('fallback');
});

test('new tasks are created with auto_run when the setting is on', function () {
    Setting::setFlag('auto_start_tasks', true);

    $project = Project::factory()->create();
    $task = app(TaskService::class)->create($project, ['title' => 'Auto feladat']);

    expect($task->auto_run)->toBeTrue();
});

test('new tasks keep auto_run false when the setting is off', function () {
    Setting::setFlag('auto_start_tasks', false);

    $project = Project::factory()->create();
    $task = app(TaskService::class)->create($project, ['title' => 'Kézi feladat']);

    expect($task->fresh()->auto_run)->toBeFalse();
});

test('create always overrides a caller-supplied auto_run with the system default', function () {
    Setting::setFlag('auto_start_tasks', false);

    $project = Project::factory()->create();
    // A hívó auto_run=true-t ad át, de create-nél a rendszer alapértelmezése (false) dönt.
    $task = app(TaskService::class)->create($project, ['title' => 'Feladat', 'auto_run' => true]);

    expect($task->auto_run)->toBeFalse();
});

test('the spending limits are saved in dollars and shown on the settings page', function () {
    $this->patch(route('app-settings.update'), [
        'daily_cost_limit_usd' => '12.5',
        'weekly_cost_limit_usd' => '60',
    ])->assertRedirect(route('app-settings.edit'));

    expect(Setting::get('daily_cost_limit_usd'))->toBe('12.5')
        ->and(Setting::get('weekly_cost_limit_usd'))->toBe('60');

    $this->get(route('app-settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('settings.daily_cost_limit_usd', '12.5')
            ->where('settings.weekly_cost_limit_usd', '60'),
        );
});

test('an empty limit field means no limit', function () {
    Setting::set('daily_cost_limit_usd', '12.5');

    $this->patch(route('app-settings.update'), ['daily_cost_limit_usd' => ''])
        ->assertRedirect(route('app-settings.edit'));

    expect(Setting::get('daily_cost_limit_usd'))->toBeNull();
});

test('a limit must be a non-negative number', function () {
    $this->patch(route('app-settings.update'), ['daily_cost_limit_usd' => '-3'])
        ->assertSessionHasErrors('daily_cost_limit_usd');

    $this->patch(route('app-settings.update'), ['weekly_cost_limit_usd' => 'sok'])
        ->assertSessionHasErrors('weekly_cost_limit_usd');
});
