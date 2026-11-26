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
 * A project rooted at a throwaway directory, so uploaded files land somewhere
 * real and writable. The directory is removed in afterEach().
 */
function attachmentProject(): Project
{
    return Project::factory()->create([
        'path' => sys_get_temp_dir().'/project-attachments-'.uniqid(),
    ]);
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/project-attachments-*') ?: [] as $directory) {
        File::deleteDirectory($directory);
    }
});

test('the attachments index lists the project files', function () {
    $project = Project::factory()->create();
    $attachment = Attachment::factory()->for($project)->create(['filename' => 'terv.txt']);
    Attachment::factory()->create(); // belongs to another project

    $this->actingAs(User::factory()->create())
        ->get(route('projects.attachments.index', $project))
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Attachments/Index')
            ->has('attachments', 1)
            ->where('attachments.0.id', $attachment->id)
            ->where('attachments.0.filename', 'terv.txt')
        );
});

test('an uploaded file is saved in the project docs/attachments folder', function () {
    $project = attachmentProject();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.attachments.store', $project), [
            'file' => UploadedFile::fake()->createWithContent('terv.txt', 'the plan'),
        ])
        ->assertRedirect(route('projects.attachments.index', $project));

    $attachment = $project->attachments()->sole();

    expect($attachment)
        ->filename->toBe('terv.txt')
        ->source->toBe('upload')
        ->disk->toBe(Attachment::PROJECT_DISK)
        ->relative_path->toBe('terv.txt');

    expect($attachment->absolutePath())->toBe($project->path.'/docs/attachments/terv.txt')
        ->and(File::get($attachment->absolutePath()))->toBe('the plan');
});

test('a project attachment can be downloaded with its original name', function () {
    $project = attachmentProject();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.attachments.store', $project), [
            'file' => UploadedFile::fake()->createWithContent('jelentés.txt', 'body'),
        ]);

    $attachment = $project->attachments()->sole();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('projects.attachments.download', [$project, $attachment]))
        ->assertOk()
        ->assertDownload('jelentes.txt')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->streamedContent())->toBe('body');
});

test('an attachment of another project cannot be downloaded through this project', function () {
    $project = Project::factory()->create();
    $attachment = Attachment::factory()->create(); // another project

    $this->actingAs(User::factory()->create())
        ->get(route('projects.attachments.download', [$project, $attachment]))
        ->assertNotFound();
});

test('destroying deletes the attachment and its folder file', function () {
    $project = attachmentProject();
    $task = Task::factory()->for($project)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.attachments.store', $project), [
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'x'),
        ]);

    $attachment = $project->attachments()->sole();
    $task->attachments()->attach($attachment->id);
    $absolute = $attachment->absolutePath();
    expect(File::exists($absolute))->toBeTrue();

    $this->actingAs(User::factory()->create())
        ->delete(route('projects.attachments.destroy', [$project, $attachment]))
        ->assertRedirect(route('projects.attachments.index', $project));

    $this->assertModelMissing($attachment);
    expect(File::exists($absolute))->toBeFalse()
        ->and($task->attachments()->count())->toBe(0);
});

test('an uploaded file is not re-imported as a duplicate by sync', function () {
    $project = attachmentProject();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.attachments.store', $project), [
            'file' => UploadedFile::fake()->createWithContent('terv.txt', 'the plan'),
        ]);

    $this->actingAs(User::factory()->create())
        ->post(route('projects.attachments.sync', $project))
        ->assertRedirect(route('projects.attachments.index', $project));

    expect($project->attachments()->count())->toBe(1)
        ->and($project->attachments()->sole()->source)->toBe('upload');
});

test('sync imports the project docs/attachments folder into the database', function () {
    $project = attachmentProject();
    $directory = $project->path.'/docs/attachments';
    File::ensureDirectoryExists($directory);
    File::put($directory.'/kep.txt', 'image bytes');

    $this->actingAs(User::factory()->create())
        ->post(route('projects.attachments.sync', $project))
        ->assertRedirect(route('projects.attachments.index', $project));

    $attachment = $project->attachments()->where('source', 'sync')->sole();
    expect($attachment)
        ->filename->toBe('kep.txt')
        ->relative_path->toBe('kep.txt');
});

test('guests cannot upload project attachments', function () {
    $project = Project::factory()->create();

    $this->post(route('projects.attachments.store', $project), [
        'file' => UploadedFile::fake()->create('a.txt'),
    ])->assertRedirect(route('login'));
});

test('the picker lists the project attachments with their absolute path as json', function () {
    $project = Project::factory()->create();
    $attachment = Attachment::factory()->for($project)->create(['filename' => 'terv.txt']);
    Attachment::factory()->create(); // belongs to another project

    $this->actingAs(User::factory()->create())
        ->getJson(route('projects.attachments.picker', $project))
        ->assertOk()
        ->assertJsonCount(1, 'attachments')
        ->assertJsonPath('attachments.0.id', $attachment->id)
        ->assertJsonPath('attachments.0.filename', 'terv.txt')
        ->assertJsonPath('attachments.0.path', $attachment->absolutePath());
});

test('the picker upload stores the file and returns it as json', function () {
    $project = attachmentProject();

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('projects.attachments.picker.upload', $project), [
            'file' => UploadedFile::fake()->createWithContent('terv.txt', 'the plan'),
        ])
        ->assertCreated()
        ->assertJsonPath('attachment.filename', 'terv.txt');

    $attachment = $project->attachments()->sole();

    expect($attachment->source)->toBe('upload');
    $response->assertJsonPath('attachment.path', $attachment->absolutePath());
});

test('guests cannot use the attachment picker', function () {
    $project = Project::factory()->create();

    $this->getJson(route('projects.attachments.picker', $project))->assertUnauthorized();
    $this->postJson(route('projects.attachments.picker.upload', $project), [
        'file' => UploadedFile::fake()->create('a.txt'),
    ])->assertUnauthorized();
});
