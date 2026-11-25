<?php

use App\Mcp\Servers\AttachmentServer;
use App\Mcp\Tools\AttachToTaskTool;
use App\Mcp\Tools\CreateAttachmentTool;
use App\Mcp\Tools\DeleteAttachmentTool;
use App\Mcp\Tools\DetachFromTaskTool;
use App\Mcp\Tools\ListAttachmentsTool;
use App\Mcp\Tools\SyncAttachmentsTool;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-attach-mcp-'.uniqid();
    $this->attachmentsPath = $this->projectsPath.'/docs/attachments';
    mkdir($this->attachmentsPath, 0755, true);

    $this->project = Project::factory()->create(['name' => 'alpha', 'path' => $this->projectsPath]);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('sync-attachments loads the project docs/attachments folder', function () {
    file_put_contents($this->attachmentsPath.'/diagram.txt', 'a diagram');

    AttachmentServer::tool(SyncAttachmentsTool::class, ['project' => 'alpha'])
        ->assertOk()
        ->assertSee('1 csatolmány betöltve a lemezről.')
        ->assertSee('diagram.txt');

    $attachment = Attachment::where('project_id', $this->project->id)->sole();
    expect($attachment->source)->toBe('sync')
        ->and($attachment->relative_path)->toBe('diagram.txt')
        ->and(File::exists($attachment->absolutePath()))->toBeTrue();
});

test('create-attachment stores a file from disk and returns its path', function () {
    $source = $this->projectsPath.'/source.txt';
    file_put_contents($source, 'ingest me');

    AttachmentServer::tool(CreateAttachmentTool::class, [
        'project' => 'alpha',
        'file_path' => $source,
    ])
        ->assertOk()
        ->assertSee('Csatolmány létrehozva.')
        ->assertSee('source.txt');

    $attachment = Attachment::where('project_id', $this->project->id)->sole();
    expect($attachment->filename)->toBe('source.txt')
        ->and($attachment->source)->toBe('upload')
        ->and(File::get($attachment->absolutePath()))->toBe('ingest me');
});

test('create-attachment errors when the file is missing', function () {
    AttachmentServer::tool(CreateAttachmentTool::class, [
        'project' => 'alpha',
        'file_path' => $this->projectsPath.'/nope.txt',
    ])->assertHasErrors();
});

test('attach-to-task links an attachment to a task and lists it', function () {
    $task = Task::factory()->for($this->project)->create();
    $attachment = Attachment::factory()->for($this->project)->create(['filename' => 'spec.txt']);

    AttachmentServer::tool(AttachToTaskTool::class, [
        'task_id' => $task->id,
        'attachment_id' => $attachment->id,
    ])->assertOk()->assertSee('spec.txt');

    expect($task->attachments()->count())->toBe(1);

    AttachmentServer::tool(ListAttachmentsTool::class, ['task_id' => $task->id])
        ->assertOk()
        ->assertSee('spec.txt');
});

test('detach-from-task removes the link but keeps the attachment', function () {
    $task = Task::factory()->for($this->project)->create();
    $attachment = Attachment::factory()->for($this->project)->create();
    $task->attachments()->attach($attachment->id);

    AttachmentServer::tool(DetachFromTaskTool::class, [
        'task_id' => $task->id,
        'attachment_id' => $attachment->id,
    ])->assertOk();

    expect($task->attachments()->count())->toBe(0);
    $this->assertModelExists($attachment);
});

test('delete-attachment removes the file and row', function () {
    $attachment = app(AttachmentService::class)->store($this->project, 'bytes', 'gone.txt');
    $absolute = $attachment->absolutePath();

    AttachmentServer::tool(DeleteAttachmentTool::class, ['attachment_id' => $attachment->id])
        ->assertOk()
        ->assertSee('gone.txt');

    $this->assertModelMissing($attachment);
    expect(File::exists($absolute))->toBeFalse();
});

test('list-attachments lists a project attachments', function () {
    Attachment::factory()->for($this->project)->create(['filename' => 'one.txt']);
    Attachment::factory()->for($this->project)->create(['filename' => 'two.txt']);

    AttachmentServer::tool(ListAttachmentsTool::class, ['project' => 'alpha'])
        ->assertOk()
        ->assertSee('one.txt')
        ->assertSee('two.txt');
});

test('sync-attachments errors on an unknown project', function () {
    AttachmentServer::tool(SyncAttachmentsTool::class, ['project' => 'nincs-ilyen'])
        ->assertHasErrors();
});
