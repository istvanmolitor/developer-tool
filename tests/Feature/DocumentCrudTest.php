<?php

use App\Models\Document;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-doccrud-'.uniqid();
    $this->docsPath = $this->projectsPath.'/docs';
    mkdir($this->docsPath, 0755, true);

    $this->project = Project::factory()->create([
        'name' => 'alpha',
        'path' => $this->projectsPath,
    ]);

    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('guests cannot reach a project document index', function () {
    auth()->logout();

    $this->get(route('projects.documents.index', 'alpha'))
        ->assertRedirect(route('login'));
});

test('the index lists the project documents', function () {
    Document::factory()->create(['project_id' => $this->project->id, 'path' => 'README.md', 'title' => 'Áttekintés']);

    $this->get(route('projects.documents.index', 'alpha'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Documents/Index')
            ->has('documents.data', 1)
            ->where('documents.data.0.path', 'README.md'),
        );
});

test('a document can be created', function () {
    $this->post(route('projects.documents.store', 'alpha'), [
        'path' => 'guides/setup.md',
        'title' => 'Telepítés',
        'content' => '# Telepítés',
    ])->assertRedirect();

    $doc = Document::sole();
    expect($doc->project_id)->toBe($this->project->id)
        ->and($doc->path)->toBe('guides/setup.md')
        ->and($doc->title)->toBe('Telepítés');
});

test('the path must be a relative markdown file', function () {
    $this->post(route('projects.documents.store', 'alpha'), ['path' => '/etc/passwd'])
        ->assertSessionHasErrors('path');

    $this->post(route('projects.documents.store', 'alpha'), ['path' => 'notes.txt'])
        ->assertSessionHasErrors('path');

    expect(Document::count())->toBe(0);
});

test('the path must be unique within the project', function () {
    Document::factory()->create(['project_id' => $this->project->id, 'path' => 'README.md']);

    $this->post(route('projects.documents.store', 'alpha'), ['path' => 'README.md', 'content' => 'x'])
        ->assertSessionHasErrors('path');
});

test('the same path is allowed across different projects', function () {
    $beta = Project::factory()->create(['name' => 'beta', 'path' => $this->projectsPath.'-beta']);
    Document::factory()->create(['project_id' => $this->project->id, 'path' => 'README.md']);

    $this->post(route('projects.documents.store', 'beta'), ['path' => 'README.md', 'content' => 'x'])
        ->assertSessionHasNoErrors();

    expect(Document::where('path', 'README.md')->count())->toBe(2);
});

test('the show page renders the markdown content as safe html', function () {
    $doc = Document::factory()->create([
        'project_id' => $this->project->id,
        'path' => 'README.md',
        'content' => "# Cím\n\n**félkövér** <script>alert(1)</script>",
    ]);

    $this->get(route('projects.documents.show', ['project' => 'alpha', 'document' => $doc->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Documents/Show')
            ->where('document.content_html', fn (string $html): bool => str_contains($html, '<h1>Cím</h1>')
                && str_contains($html, '<strong>félkövér</strong>')
                && ! str_contains($html, '<script>'),
            ),
        );
});

test('a document can be updated', function () {
    $doc = Document::factory()->create(['project_id' => $this->project->id, 'path' => 'README.md', 'content' => 'régi']);

    $this->put(route('projects.documents.update', ['project' => 'alpha', 'document' => $doc->id]), [
        'path' => 'README.md',
        'title' => 'Frissített cím',
        'content' => 'új',
    ])->assertRedirect();

    expect($doc->fresh())
        ->title->toBe('Frissített cím')
        ->content->toBe('új');
});

test('a document can be deleted', function () {
    $doc = Document::factory()->create(['project_id' => $this->project->id]);

    $this->delete(route('projects.documents.destroy', ['project' => 'alpha', 'document' => $doc->id]))
        ->assertRedirect(route('projects.documents.index', 'alpha'));

    expect(Document::count())->toBe(0);
});

test('document is scoped to its project', function () {
    $beta = Project::factory()->create(['name' => 'beta', 'path' => $this->projectsPath.'-beta']);
    $doc = Document::factory()->create(['project_id' => $beta->id]);

    // The doc belongs to beta, so reaching it through alpha must 404.
    $this->get(route('projects.documents.show', ['project' => 'alpha', 'document' => $doc->id]))
        ->assertNotFound();
});

test('the sync button imports markdown files from disk', function () {
    file_put_contents($this->docsPath.'/README.md', "# Áttekintés\n\nTartalom.");

    $this->post(route('projects.documents.sync', 'alpha'))
        ->assertRedirect(route('projects.documents.index', 'alpha'));

    $doc = Document::sole();
    expect($doc->path)->toBe('README.md')
        ->and($doc->title)->toBe('Áttekintés')
        ->and($doc->content)->toContain('Tartalom.');
});

test('the document can be downloaded as a PDF', function () {
    Document::factory()->create([
        'project_id' => $this->project->id,
        'path' => 'README.md',
        'title' => 'Áttekintés',
        'content' => "# Áttekintés\n\nTartalom.",
    ]);

    $response = $this->get(route('projects.documents.pdf', 'alpha'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('alpha-dokumentumok.pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});
