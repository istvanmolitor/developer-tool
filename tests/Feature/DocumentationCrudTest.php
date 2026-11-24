<?php

use App\Models\Documentation;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-doccrud-'.uniqid();
    $this->docsPath = $this->projectsPath.'/docs/documentations';
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

test('guests cannot reach a project documentation index', function () {
    auth()->logout();

    $this->get(route('projects.documentations.index', 'alpha'))
        ->assertRedirect(route('login'));
});

test('the index lists the project documentations', function () {
    Documentation::factory()->create(['project_id' => $this->project->id, 'path' => 'README.md', 'title' => 'Áttekintés']);

    $this->get(route('projects.documentations.index', 'alpha'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Documentations/Index')
            ->has('documentations', 1)
            ->where('documentations.0.path', 'README.md'),
        );
});

test('a documentation can be created', function () {
    $this->post(route('projects.documentations.store', 'alpha'), [
        'path' => 'guides/setup.md',
        'title' => 'Telepítés',
        'content' => '# Telepítés',
    ])->assertRedirect();

    $doc = Documentation::sole();
    expect($doc->project_id)->toBe($this->project->id)
        ->and($doc->path)->toBe('guides/setup.md')
        ->and($doc->title)->toBe('Telepítés');
});

test('the path must be a relative markdown file', function () {
    $this->post(route('projects.documentations.store', 'alpha'), ['path' => '/etc/passwd'])
        ->assertSessionHasErrors('path');

    $this->post(route('projects.documentations.store', 'alpha'), ['path' => 'notes.txt'])
        ->assertSessionHasErrors('path');

    expect(Documentation::count())->toBe(0);
});

test('the path must be unique within the project', function () {
    Documentation::factory()->create(['project_id' => $this->project->id, 'path' => 'README.md']);

    $this->post(route('projects.documentations.store', 'alpha'), ['path' => 'README.md', 'content' => 'x'])
        ->assertSessionHasErrors('path');
});

test('the same path is allowed across different projects', function () {
    $beta = Project::factory()->create(['name' => 'beta', 'path' => $this->projectsPath.'-beta']);
    Documentation::factory()->create(['project_id' => $this->project->id, 'path' => 'README.md']);

    $this->post(route('projects.documentations.store', 'beta'), ['path' => 'README.md', 'content' => 'x'])
        ->assertSessionHasNoErrors();

    expect(Documentation::where('path', 'README.md')->count())->toBe(2);
});

test('the show page renders the markdown content as safe html', function () {
    $doc = Documentation::factory()->create([
        'project_id' => $this->project->id,
        'path' => 'README.md',
        'content' => "# Cím\n\n**félkövér** <script>alert(1)</script>",
    ]);

    $this->get(route('projects.documentations.show', ['project' => 'alpha', 'documentation' => $doc->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Documentations/Show')
            ->where('documentation.content_html', fn (string $html): bool => str_contains($html, '<h1>Cím</h1>')
                && str_contains($html, '<strong>félkövér</strong>')
                && ! str_contains($html, '<script>'),
            ),
        );
});

test('a documentation can be updated', function () {
    $doc = Documentation::factory()->create(['project_id' => $this->project->id, 'path' => 'README.md', 'content' => 'régi']);

    $this->put(route('projects.documentations.update', ['project' => 'alpha', 'documentation' => $doc->id]), [
        'path' => 'README.md',
        'title' => 'Frissített cím',
        'content' => 'új',
    ])->assertRedirect();

    expect($doc->fresh())
        ->title->toBe('Frissített cím')
        ->content->toBe('új');
});

test('a documentation can be deleted', function () {
    $doc = Documentation::factory()->create(['project_id' => $this->project->id]);

    $this->delete(route('projects.documentations.destroy', ['project' => 'alpha', 'documentation' => $doc->id]))
        ->assertRedirect(route('projects.documentations.index', 'alpha'));

    expect(Documentation::count())->toBe(0);
});

test('documentation is scoped to its project', function () {
    $beta = Project::factory()->create(['name' => 'beta', 'path' => $this->projectsPath.'-beta']);
    $doc = Documentation::factory()->create(['project_id' => $beta->id]);

    // The doc belongs to beta, so reaching it through alpha must 404.
    $this->get(route('projects.documentations.show', ['project' => 'alpha', 'documentation' => $doc->id]))
        ->assertNotFound();
});

test('the sync button imports markdown files from disk', function () {
    file_put_contents($this->docsPath.'/README.md', "# Áttekintés\n\nTartalom.");

    $this->post(route('projects.documentations.sync', 'alpha'))
        ->assertRedirect(route('projects.documentations.index', 'alpha'));

    $doc = Documentation::sole();
    expect($doc->path)->toBe('README.md')
        ->and($doc->title)->toBe('Áttekintés')
        ->and($doc->content)->toContain('Tartalom.');
});

test('the documentation can be downloaded as a PDF', function () {
    Documentation::factory()->create([
        'project_id' => $this->project->id,
        'path' => 'README.md',
        'title' => 'Áttekintés',
        'content' => "# Áttekintés\n\nTartalom.",
    ]);

    $response = $this->get(route('projects.documentations.pdf', 'alpha'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('alpha-dokumentacio.pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});
