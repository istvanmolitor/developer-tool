<?php

use App\Models\Attachment;
use App\Models\Project;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * A throwaway projects directory, so attachment writes land somewhere real and
 * writable. Attachments live in `<projects_path>/attachments/<name>`, next to
 * the project directories rather than inside them, so the tests point the
 * configured projects path at this temp folder. It is removed in afterEach().
 */
beforeEach(function (): void {
    $this->projectsPath = sys_get_temp_dir().'/attach-'.uniqid();
    config(['developer-tool.projects_path' => $this->projectsPath]);
});

afterEach(function (): void {
    File::deleteDirectory($this->projectsPath);
});

/**
 * Absolute path of a project's attachments folder, mirroring the service.
 */
function attachmentsDir(Project $project): string
{
    return config('developer-tool.projects_path').'/attachments/'.$project->name;
}

test('store writes the bytes into the central attachments folder and records the row', function () {
    $project = Project::factory()->create();

    $attachment = app(AttachmentService::class)->store($project, 'hello world', 'note.txt', 'text/plain');

    expect($attachment)
        ->project_id->toBe($project->id)
        ->filename->toBe('note.txt')
        ->mime_type->toBe('text/plain')
        ->size->toBe(11)
        ->source->toBe('upload')
        ->disk->toBe(Attachment::PROJECT_DISK)
        ->relative_path->toBe('note.txt');

    expect($attachment->absolutePath())->toBe(attachmentsDir($project).'/note.txt')
        ->and(File::get($attachment->absolutePath()))->toBe('hello world');
});

test('the system project also stores in the central attachments folder, not inside itself', function () {
    $project = Project::factory()->create();
    $project->forceFill(['is_system' => true])->saveQuietly();

    $attachment = app(AttachmentService::class)->store($project, 'x', 'sys.txt');

    expect($attachment->absolutePath())->toBe(attachmentsDir($project).'/sys.txt')
        ->and(File::get($attachment->absolutePath()))->toBe('x');
});

test('uploading the same filename twice keeps both files', function () {
    $project = Project::factory()->create();
    $service = app(AttachmentService::class);

    $first = $service->store($project, 'one', 'dup.txt');
    $second = $service->store($project, 'two', 'dup.txt');

    expect($first->relative_path)->toBe('dup.txt')
        ->and($second->relative_path)->toBe('dup (1).txt')
        ->and(File::get($second->absolutePath()))->toBe('two');
});

test('deleting an attachment removes its file', function () {
    $project = Project::factory()->create();
    $attachment = app(AttachmentService::class)->store($project, 'bytes', 'a.txt');
    $absolute = $attachment->absolutePath();

    expect(File::exists($absolute))->toBeTrue();

    $attachment->delete();

    expect(File::exists($absolute))->toBeFalse();
});

test('deleting a project removes its attachment files and rows', function () {
    $project = Project::factory()->create();
    $attachment = app(AttachmentService::class)->store($project, 'bytes', 'a.txt');
    $absolute = $attachment->absolutePath();

    $project->delete();

    $this->assertModelMissing($attachment);
    expect(File::exists($absolute))->toBeFalse();
});

test('syncProject imports the attachments folder as rows', function () {
    $project = Project::factory()->create();
    $directory = attachmentsDir($project);
    File::ensureDirectoryExists($directory.'/sub');
    File::put($directory.'/a.txt', 'alpha');
    File::put($directory.'/sub/b.txt', 'beta');

    $synced = app(AttachmentService::class)->syncProject($project);

    expect($synced)->toHaveCount(2)
        ->and($synced->pluck('relative_path')->all())->toContain('a.txt', 'sub/b.txt')
        ->and($synced->every(fn (Attachment $a): bool => $a->source === 'sync'))->toBeTrue()
        ->and($synced->firstWhere('relative_path', 'a.txt')->absolutePath())->toBe($directory.'/a.txt');
});

test('syncProject does not duplicate an already uploaded file', function () {
    $project = Project::factory()->create();
    $service = app(AttachmentService::class);

    $uploaded = $service->store($project, 'body', 'report.txt');

    $service->syncProject($project);

    expect($project->attachments()->where('relative_path', 'report.txt')->count())->toBe(1)
        ->and($project->attachments()->where('relative_path', 'report.txt')->sole())
        ->id->toBe($uploaded->id)
        ->source->toBe('upload');
});

test('syncProject prunes rows whose source file disappeared', function () {
    $project = Project::factory()->create();
    $directory = attachmentsDir($project);
    File::ensureDirectoryExists($directory);
    File::put($directory.'/keep.txt', 'keep');
    File::put($directory.'/gone.txt', 'gone');

    $service = app(AttachmentService::class);
    expect($service->syncProject($project))->toHaveCount(2);

    File::delete($directory.'/gone.txt');
    $second = $service->syncProject($project);

    expect($second)->toHaveCount(1)
        ->and($second->first()->relative_path)->toBe('keep.txt')
        ->and($project->attachments()->where('relative_path', 'gone.txt')->exists())->toBeFalse();
});

test('syncProject refreshes a sync row when its file changes', function () {
    $project = Project::factory()->create();
    $directory = attachmentsDir($project);
    File::ensureDirectoryExists($directory);
    File::put($directory.'/a.txt', 'version one');

    $service = app(AttachmentService::class);
    $first = $service->syncProject($project)->sole();

    File::put($directory.'/a.txt', 'version two now');
    $second = $service->syncProject($project)->sole();

    expect($second->id)->toBe($first->id)
        ->and($second->size)->toBe(15)
        ->and($second->checksum)->toBe(hash('sha256', 'version two now'))
        ->and(File::get($second->absolutePath()))->toBe('version two now');
});
