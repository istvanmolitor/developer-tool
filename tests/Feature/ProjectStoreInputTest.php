<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath, 0755, true);

    config(['developer-tool.projects_path' => $this->projectsPath]);

    Queue::fake();
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('the create form rejects malformed or unsafe input with validation errors', function (array $input, string $field) {
    mkdir($this->projectsPath.'/on-disk');

    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), $input)
        ->assertSessionHasErrors($field);

    expect(Project::count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'mode as an array' => [['mode' => ['new'], 'name' => 'fresh'], 'mode'],
    'dot-dot name' => [['mode' => 'new', 'name' => '..'], 'name'],
    'git target folder already on disk' => [['mode' => 'git', 'git_url' => 'https://github.com/acme/on-disk.git'], 'name'],
    'git url as an option' => [['mode' => 'git', 'name' => 'fresh', 'git_url' => '--upload-pack=touch /tmp/pwned'], 'git_url'],
]);
