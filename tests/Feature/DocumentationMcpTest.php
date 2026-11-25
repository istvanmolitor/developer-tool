<?php

use App\Mcp\Servers\DocumentationServer;
use App\Mcp\Tools\SyncDocumentationTool;
use App\Models\Documentation;
use App\Models\Project;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-docs-mcp-'.uniqid();
    $this->docsPath = $this->projectsPath.'/docs/documentations';
    mkdir($this->docsPath, 0755, true);

    $this->project = Project::factory()->create(['name' => 'alpha', 'path' => $this->projectsPath]);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('the server registers the sync tool', function () {
    DocumentationServer::tool(SyncDocumentationTool::class);
})->throwsNoExceptions();

test('sync-documentation loads the project docs folder into the database', function () {
    file_put_contents($this->docsPath.'/README.md', "# Áttekintés\n\nA brief.");

    DocumentationServer::tool(SyncDocumentationTool::class, ['project' => 'alpha'])
        ->assertOk()
        ->assertSee('1 dokumentáció betöltve a lemezről.')
        ->assertSee('README.md');

    $doc = Documentation::where('project_id', $this->project->id)->sole();
    expect($doc->path)->toBe('README.md')
        ->and($doc->title)->toBe('Áttekintés');
});

test('sync-documentation prunes rows whose files were removed', function () {
    Documentation::factory()->create(['project_id' => $this->project->id, 'path' => 'stale.md']);

    DocumentationServer::tool(SyncDocumentationTool::class, ['project' => 'alpha'])->assertOk();

    expect(Documentation::where('project_id', $this->project->id)->count())->toBe(0);
});

test('sync-documentation errors on an unknown project', function () {
    DocumentationServer::tool(SyncDocumentationTool::class, ['project' => 'nincs-ilyen'])
        ->assertHasErrors();
});
