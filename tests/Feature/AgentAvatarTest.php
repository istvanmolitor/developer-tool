<?php

use App\Models\Agent;
use App\Models\User;
use App\Services\AgentAvatarService;
use Database\Seeders\AgentAvatarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keep the agent definition files the controller writes out of the repo.
    $this->agentsPath = sys_get_temp_dir().'/agent-avatars-'.uniqid();
    mkdir($this->agentsPath, 0755, true);
    config(['developer-tool.agents_path' => $this->agentsPath]);

    Storage::fake('local');

    $this->actingAs(User::factory()->create());
});

afterEach(function (): void {
    exec('rm -rf '.escapeshellarg($this->agentsPath));
});

/**
 * The fields the agent form always posts, so a test only has to name what it
 * is actually about.
 *
 * @return array<string, mixed>
 */
function agentFormPayload(Agent $agent, array $overrides = []): array
{
    return [
        '_method' => 'put',
        'name' => $agent->name,
        'slug' => $agent->slug,
        'system_prompt' => $agent->system_prompt,
        ...$overrides,
    ];
}

test('an uploaded profile picture is stored and served from the agent', function () {
    $agent = Agent::factory()->create();

    $this->post(route('agents.update', $agent), agentFormPayload($agent, [
        'avatar' => UploadedFile::fake()->image('arc.png'),
    ]))->assertRedirect(route('agents.profile', $agent));

    $agent->refresh();

    expect($agent->avatar_path)->toStartWith(AgentAvatarService::DIRECTORY.'/')
        ->and($agent->avatar_url)->toContain(route('agents.avatar', $agent, false));

    Storage::disk('local')->assertExists($agent->avatar_path);

    $this->get(route('agents.avatar', $agent))
        ->assertOk()
        ->assertHeader('x-content-type-options', 'nosniff');
});

test('uploading a new picture replaces the previous file', function () {
    $agent = Agent::factory()->create();

    $this->post(route('agents.update', $agent), agentFormPayload($agent, [
        'avatar' => UploadedFile::fake()->image('elso.png'),
    ]));

    $first = $agent->refresh()->avatar_path;

    $this->post(route('agents.update', $agent), agentFormPayload($agent, [
        'avatar' => UploadedFile::fake()->image('masodik.png'),
    ]));

    $second = $agent->refresh()->avatar_path;

    expect($second)->not->toBe($first);
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);
});

test('the picture can be removed from the editor', function () {
    $agent = Agent::factory()->create();

    $this->post(route('agents.update', $agent), agentFormPayload($agent, [
        'avatar' => UploadedFile::fake()->image('arc.png'),
    ]));

    $path = $agent->refresh()->avatar_path;

    $this->post(route('agents.update', $agent), agentFormPayload($agent, [
        'remove_avatar' => true,
    ]));

    expect($agent->refresh()->avatar_path)->toBeNull()
        ->and($agent->avatar_url)->toBeNull();

    Storage::disk('local')->assertMissing($path);
});

test('only image files are accepted as a profile picture', function () {
    $agent = Agent::factory()->create();

    $this->post(route('agents.update', $agent), agentFormPayload($agent, [
        'avatar' => UploadedFile::fake()->create('terv.pdf', 10, 'application/pdf'),
    ]))->assertSessionHasErrors('avatar');

    expect($agent->refresh()->avatar_path)->toBeNull();
});

test('an agent without a picture has no avatar route to serve', function () {
    $agent = Agent::factory()->create();

    expect($agent->avatar_url)->toBeNull();

    $this->get(route('agents.avatar', $agent))->assertNotFound();
});

test('the seeder draws a default picture for every agent that has none', function () {
    $agent = Agent::factory()->create(['name' => 'Adatbázis-szakértő', 'slug' => 'database-expert']);

    $this->seed(AgentAvatarSeeder::class);

    $agent->refresh();

    expect($agent->avatar_path)->toBe(AgentAvatarService::DIRECTORY.'/database-expert-default.png');

    Storage::disk('local')->assertExists($agent->avatar_path);

    $image = imagecreatefromstring((string) Storage::disk('local')->get($agent->avatar_path));

    expect($image)->not->toBeFalse()
        ->and(imagesx($image))->toBe(256);
});
