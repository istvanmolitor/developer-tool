<?php

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * A project rooted at a throwaway directory, so uploaded task files land
 * somewhere real and writable. The directory is removed in afterEach().
 */
function taskProject(): Project
{
    return Project::factory()->create([
        'path' => sys_get_temp_dir().'/task-attachments-'.uniqid(),
    ]);
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/task-attachments-*') ?: [] as $directory) {
        File::deleteDirectory($directory);
    }
});

test('a file can be uploaded and is attached to the task', function () {
    $project = taskProject();
    $task = Task::factory()->for($project)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.tasks.attachments.store', [$project, $task]), [
            'file' => UploadedFile::fake()->createWithContent('terv.txt', 'the plan'),
        ])
        ->assertRedirect();

    $attachment = $task->attachments()->sole();

    expect($attachment)
        ->filename->toBe('terv.txt')
        ->source->toBe('upload')
        ->project_id->toBe($project->id);
    expect(File::get($attachment->absolutePath()))->toBe('the plan');
});

test('an uploaded attachment can be downloaded with its original name', function () {
    $project = taskProject();
    $task = Task::factory()->for($project)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.tasks.attachments.store', [$project, $task]), [
            'file' => UploadedFile::fake()->createWithContent('jelentés.txt', 'body'),
        ]);

    $attachment = $task->attachments()->sole();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('projects.tasks.attachments.download', [$project, $task, $attachment]))
        ->assertOk()
        ->assertDownload('jelentes.txt')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toContain("filename*=utf-8''jelent%C3%A9s.txt")
        ->and($response->streamedContent())->toBe('body');
});

test('an attachment of another task cannot be downloaded through this task', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    $otherTask = Task::factory()->for($project)->create();
    $attachment = Attachment::factory()->for($project)->create();
    $otherTask->attachments()->attach($attachment->id);

    $this->actingAs(User::factory()->create())
        ->get(route('projects.tasks.attachments.download', [$project, $task, $attachment]))
        ->assertNotFound();
});

test('destroying detaches and deletes an attachment that is no longer referenced', function () {
    $project = taskProject();
    $task = Task::factory()->for($project)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.tasks.attachments.store', [$project, $task]), [
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'x'),
        ]);

    $attachment = $task->attachments()->sole();
    $absolute = $attachment->absolutePath();

    $this->actingAs(User::factory()->create())
        ->delete(route('projects.tasks.attachments.destroy', [$project, $task, $attachment]))
        ->assertRedirect();

    $this->assertModelMissing($attachment);
    expect(File::exists($absolute))->toBeFalse()
        ->and($task->attachments()->count())->toBe(0);
});

test('destroying only detaches when the attachment is still referenced elsewhere', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    $otherTask = Task::factory()->for($project)->create();
    $attachment = Attachment::factory()->for($project)->create();
    $task->attachments()->attach($attachment->id);
    $otherTask->attachments()->attach($attachment->id);

    $this->actingAs(User::factory()->create())
        ->delete(route('projects.tasks.attachments.destroy', [$project, $task, $attachment]))
        ->assertRedirect();

    $this->assertModelExists($attachment);
    expect($task->attachments()->count())->toBe(0)
        ->and($otherTask->attachments()->count())->toBe(1);
});

test('guests cannot upload attachments', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();

    $this->post(route('projects.tasks.attachments.store', [$project, $task]), [
        'file' => UploadedFile::fake()->create('a.txt'),
    ])->assertRedirect(route('login'));
});
