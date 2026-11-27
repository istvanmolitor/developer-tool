<?php

use App\Mcp\Servers\DocumentServer;
use App\Mcp\Tools\SyncDocumentsTool;
use App\Models\Document;
use App\Models\Project;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-docs-mcp-'.uniqid();
    $this->docsPath = $this->projectsPath.'/docs';
    mkdir($this->docsPath, 0755, true);

    $this->project = Project::factory()->create(['name' => 'alpha', 'path' => $this->projectsPath]);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('the server registers the sync tool', function () {
    DocumentServer::tool(SyncDocumentsTool::class);
})->throwsNoExceptions();

test('sync-documents loads the project docs folder into the database', function () {
    file_put_contents($this->docsPath.'/README.md', "# Áttekintés\n\nA brief.");

    DocumentServer::tool(SyncDocumentsTool::class, ['project' => 'alpha'])
        ->assertOk()
        ->assertSee('1 dokumentum betöltve a lemezről.')
        ->assertSee('README.md');

    $doc = Document::where('project_id', $this->project->id)->sole();
    expect($doc->path)->toBe('README.md')
        ->and($doc->title)->toBe('Áttekintés');
});

test('sync-documents prunes rows whose files were removed', function () {
    Document::factory()->create(['project_id' => $this->project->id, 'path' => 'stale.md']);

    DocumentServer::tool(SyncDocumentsTool::class, ['project' => 'alpha'])->assertOk();

    expect(Document::where('project_id', $this->project->id)->count())->toBe(0);
});

test('sync-documents errors on an unknown project', function () {
    DocumentServer::tool(SyncDocumentsTool::class, ['project' => 'nincs-ilyen'])
        ->assertHasErrors();
});
