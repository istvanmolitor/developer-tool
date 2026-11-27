<?php

use App\Models\User;
use App\Services\UserAvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * The fields the profile form always posts, so a test only has to name what it
 * is actually about.
 *
 * @return array<string, mixed>
 */
function profileFormPayload(User $user, array $overrides = []): array
{
    return [
        'name' => $user->name,
        'email' => $user->email,
        ...$overrides,
    ];
}

test('an uploaded profile picture is stored and served to the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), profileFormPayload($user, [
            'avatar' => UploadedFile::fake()->image('arc.png'),
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatar_path)->toStartWith(UserAvatarService::DIRECTORY.'/')
        ->and($user->avatar_url)->toContain(route('profile.avatar', [], false));

    Storage::disk('local')->assertExists($user->avatar_path);

    $this->get(route('profile.avatar'))
        ->assertOk()
        ->assertHeader('x-content-type-options', 'nosniff');
});

test('uploading a new picture replaces the previous file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), profileFormPayload($user, [
        'avatar' => UploadedFile::fake()->image('elso.png'),
    ]));

    $first = $user->refresh()->avatar_path;

    $this->patch(route('profile.update'), profileFormPayload($user, [
        'avatar' => UploadedFile::fake()->image('masodik.png'),
    ]));

    $second = $user->refresh()->avatar_path;

    expect($second)->not->toBe($first);
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);
});

test('the picture can be removed from the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), profileFormPayload($user, [
        'avatar' => UploadedFile::fake()->image('arc.png'),
    ]));

    $path = $user->refresh()->avatar_path;

    $this->patch(route('profile.update'), profileFormPayload($user, [
        'remove_avatar' => '1',
    ]));

    expect($user->refresh()->avatar_path)->toBeNull()
        ->and($user->avatar_url)->toBeNull();

    Storage::disk('local')->assertMissing($path);
});

test('only image files are accepted as a profile picture', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), profileFormPayload($user, [
            'avatar' => UploadedFile::fake()->create('terv.pdf', 10, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('avatar');

    expect($user->refresh()->avatar_path)->toBeNull();
});

test('a user without a picture has no avatar route to serve', function () {
    $user = User::factory()->create();

    expect($user->avatar_url)->toBeNull();

    $this->actingAs($user)->get(route('profile.avatar'))->assertNotFound();
});
