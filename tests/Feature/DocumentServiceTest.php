<?php

use App\Models\Document;
use App\Models\Project;
use App\Services\DocumentService;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-docs-'.uniqid();
    $this->docsPath = $this->projectsPath.'/docs';
    mkdir($this->docsPath, 0755, true);

    $this->project = Project::factory()->create(['path' => $this->projectsPath]);
    $this->service = app(DocumentService::class);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('it imports markdown files from the project docs folder', function () {
    file_put_contents($this->docsPath.'/README.md', "# Áttekintés\n\nA rendszer leírása.");
    mkdir($this->docsPath.'/guides', 0755, true);
    file_put_contents($this->docsPath.'/guides/setup.md', "# Telepítés\n\nLépések.");

    $docs = $this->service->syncProject($this->project);

    expect($docs)->toHaveCount(2);

    $readme = Document::where('project_id', $this->project->id)->where('path', 'README.md')->sole();
    expect($readme->title)->toBe('Áttekintés')
        ->and($readme->content)->toContain('A rendszer leírása.');

    $setup = Document::where('path', 'guides/setup.md')->sole();
    expect($setup->title)->toBe('Telepítés');
});

test('the documentations subfolder is read as part of the docs tree', function () {
    mkdir($this->docsPath.'/documentations/analysis', 0755, true);
    file_put_contents($this->docsPath.'/documentations/README.md', "# Dokumentáció\n\nAz agent írta.");
    file_put_contents($this->docsPath.'/documentations/analysis/piac.md', "# Piac\n\nElemzés.");
    file_put_contents($this->docsPath.'/terv.md', "# Terv\n\nGyökérben.");

    $this->service->syncProject($this->project);

    expect(Document::where('project_id', $this->project->id)->pluck('path')->sort()->values()->all())
        ->toBe(['documentations/README.md', 'documentations/analysis/piac.md', 'terv.md']);
});

test('it prefers the front matter title over the first heading', function () {
    file_put_contents(
        $this->docsPath.'/intro.md',
        "---\ntitle: Kézi cím\n---\n# Másik cím\n\nSzöveg.",
    );

    $this->service->syncProject($this->project);

    expect(Document::where('path', 'intro.md')->sole()->title)->toBe('Kézi cím');
});

test('re-syncing updates existing rows instead of duplicating them', function () {
    file_put_contents($this->docsPath.'/README.md', "# Első\n\nv1");
    $this->service->syncProject($this->project);

    file_put_contents($this->docsPath.'/README.md', "# Első\n\nv2");
    $this->service->syncProject($this->project);

    $docs = Document::where('project_id', $this->project->id)->get();
    expect($docs)->toHaveCount(1)
        ->and($docs->first()->content)->toContain('v2');
});

test('it prunes rows whose files were removed', function () {
    file_put_contents($this->docsPath.'/keep.md', '# Keep');
    file_put_contents($this->docsPath.'/drop.md', '# Drop');
    $this->service->syncProject($this->project);

    unlink($this->docsPath.'/drop.md');
    $this->service->syncProject($this->project);

    expect(Document::where('project_id', $this->project->id)->pluck('path')->all())
        ->toBe(['keep.md']);
});

test('it ignores non-markdown files', function () {
    file_put_contents($this->docsPath.'/README.md', '# Doc');
    file_put_contents($this->docsPath.'/notes.txt', 'plain');
    file_put_contents($this->docsPath.'/image.png', 'binary');

    $docs = $this->service->syncProject($this->project);

    expect($docs)->toHaveCount(1)
        ->and($docs->first()->path)->toBe('README.md');
});

test('a system project reads its docs from the application own folder', function () {
    $system = Project::factory()->create(['path' => '/some/unrelated/path']);
    $system->forceFill(['is_system' => true])->save();

    expect($this->service->directoryFor($system))
        ->toBe(base_path('docs'));
});

test('a normal project reads its docs from its own path', function () {
    expect($this->service->directoryFor($this->project))
        ->toBe($this->projectsPath.'/docs');
});

test('a missing folder clears any previously imported docs and returns empty', function () {
    Document::factory()->create(['project_id' => $this->project->id, 'path' => 'stale.md']);
    exec('rm -rf '.escapeshellarg($this->projectsPath.'/docs'));

    $docs = $this->service->syncProject($this->project);

    expect($docs)->toBeEmpty()
        ->and(Document::where('project_id', $this->project->id)->count())->toBe(0);
});
