<?php

use App\Models\Attachment;
use App\Models\Project;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * A project rooted at a throwaway directory, so writes land somewhere real and
 * writable. The directory is removed in afterEach().
 */
function tempProject(): Project
{
    return Project::factory()->create([
        'path' => sys_get_temp_dir().'/attach-'.uniqid(),
    ]);
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/attach-*') ?: [] as $directory) {
        File::deleteDirectory($directory);
    }
    File::deleteDirectory(storage_path('attachments'));
});

test('store writes the bytes into the project folder and records the row', function () {
    $project = tempProject();

    $attachment = app(AttachmentService::class)->store($project, 'hello world', 'note.txt', 'text/plain');

    expect($attachment)
        ->project_id->toBe($project->id)
        ->filename->toBe('note.txt')
        ->mime_type->toBe('text/plain')
        ->size->toBe(11)
        ->source->toBe('upload')
        ->disk->toBe(Attachment::PROJECT_DISK)
        ->relative_path->toBe('note.txt');

    expect($attachment->absolutePath())->toBe($project->path.'/docs/attachments/note.txt')
        ->and(File::get($attachment->absolutePath()))->toBe('hello world');
});

test('store writes the system project files under storage', function () {
    $project = tempProject();
    $project->forceFill(['is_system' => true])->saveQuietly();

    $attachment = app(AttachmentService::class)->store($project, 'x', 'sys.txt');

    expect($attachment->absolutePath())->toBe(storage_path('attachments/sys.txt'))
        ->and(File::get($attachment->absolutePath()))->toBe('x');
});

test('uploading the same filename twice keeps both files', function () {
    $project = tempProject();
    $service = app(AttachmentService::class);

    $first = $service->store($project, 'one', 'dup.txt');
    $second = $service->store($project, 'two', 'dup.txt');

    expect($first->relative_path)->toBe('dup.txt')
        ->and($second->relative_path)->toBe('dup (1).txt')
        ->and(File::get($second->absolutePath()))->toBe('two');
});

test('deleting an attachment removes its file', function () {
    $project = tempProject();
    $attachment = app(AttachmentService::class)->store($project, 'bytes', 'a.txt');
    $absolute = $attachment->absolutePath();

    expect(File::exists($absolute))->toBeTrue();

    $attachment->delete();

    expect(File::exists($absolute))->toBeFalse();
});

test('deleting a project removes its attachment files and rows', function () {
    $project = tempProject();
    $attachment = app(AttachmentService::class)->store($project, 'bytes', 'a.txt');
    $absolute = $attachment->absolutePath();

    $project->delete();

    $this->assertModelMissing($attachment);
    expect(File::exists($absolute))->toBeFalse();
});

test('syncProject imports the docs/attachments folder as rows', function () {
    $project = tempProject();
    $directory = $project->path.'/docs/attachments';
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
    $project = tempProject();
    $service = app(AttachmentService::class);

    $uploaded = $service->store($project, 'body', 'report.txt');

    $service->syncProject($project);

    expect($project->attachments()->where('relative_path', 'report.txt')->count())->toBe(1)
        ->and($project->attachments()->where('relative_path', 'report.txt')->sole())
        ->id->toBe($uploaded->id)
        ->source->toBe('upload');
});

test('syncProject prunes rows whose source file disappeared', function () {
    $project = tempProject();
    $directory = $project->path.'/docs/attachments';
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
    $project = tempProject();
    $directory = $project->path.'/docs/attachments';
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
