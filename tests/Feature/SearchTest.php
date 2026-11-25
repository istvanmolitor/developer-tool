<?php

use App\Enums\PromptRunStatus;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\IncomingEmail;
use App\Models\Mailbox;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\Search\SearchUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'alpha']);
});

test('guests cannot reach the search', function () {
    $this->get(route('search.index', ['q' => 'x']))->assertRedirect(route('login'));
    $this->get(route('search.suggest', ['q' => 'xx']))->assertRedirect(route('login'));
});

test('the search page finds every type, counts them and filters by type', function () {
    $task = Task::factory()->for($this->project)->create(['title' => 'Számlázó modul', 'description' => null]);
    TaskComment::factory()->create(['task_id' => $task->id, 'body' => 'A számlázó hibás.']);
    Task::factory()->for($this->project)->create(['title' => 'Egészen más']);

    $this->actingAs(User::factory()->create())
        ->get(route('search.index', ['q' => 'szamlazo']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Search/Index')
            ->where('results.total', 2)
            ->where('types.0.value', 'task')
            ->where('types.0.count', 1)
            ->where('types.5.value', 'comment')
            ->where('types.5.count', 1)
        );

    $this->actingAs(User::factory()->create())
        ->get(route('search.index', ['q' => 'szamlazo', 'type' => 'comment']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.total', 1)
            ->where('results.data.0.type', 'comment')
            ->where('results.data.0.url', "/projects/alpha/tasks/{$task->id}#comment-".TaskComment::first()->id)
            ->where('types.0.count', 1)
        );
});

test('the suggestions link to the found items with the match highlighted', function () {
    $task = Task::factory()->for($this->project)->create(['title' => 'Kereső <b>fejléc</b>']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('search.suggest', ['q' => 'kereso']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('results.0.url', "/projects/alpha/tasks/{$task->id}")
        ->assertJsonPath('results.0.project', 'alpha')
        ->assertJsonPath('results.0.title_html', '<mark>Kereső</mark> &lt;b&gt;fejléc&lt;/b&gt;');
});

test('the suggestions need at least two characters', function () {
    Task::factory()->for($this->project)->create(['title' => 'a']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('search.suggest', ['q' => 'a']))
        ->assertExactJson(['results' => [], 'total' => 0]);
});

test('the task list is filtered by text and status and paginated', function () {
    Task::factory()->for($this->project)->count(22)->create(['title' => 'Import feladat', 'status' => TaskStatus::Todo]);
    Task::factory()->for($this->project)->create(['title' => 'Import kész', 'status' => TaskStatus::Done]);
    Task::factory()->for(Project::factory())->create(['title' => 'Import másik projekt']);

    $this->actingAs(User::factory()->create())
        ->get(route('projects.tasks.index', ['project' => 'alpha', 'q' => 'import', 'status' => 'todo', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tasks.total', 22)
            ->where('tasks.current_page', 2)
            ->has('tasks.data', 2)
            ->where('filters.status', 'todo')
            ->where('tasks.next_page_url', null)
        );
});

test('the agent task list shows done tasks only when asked', function () {
    $agent = Agent::factory()->create();
    Task::factory()->for($this->project)->create(['agent_id' => $agent->id, 'status' => TaskStatus::Todo]);
    Task::factory()->for($this->project)->create(['agent_id' => $agent->id, 'status' => TaskStatus::Done]);

    $this->actingAs(User::factory()->create())
        ->get(route('agents.tasks', $agent))
        ->assertInertia(fn (Assert $page) => $page->where('tasks.total', 1)->where('filters.status', 'open'));

    $this->actingAs(User::factory()->create())
        ->get(route('agents.tasks', ['agent' => $agent, 'status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->where('tasks.total', 2));
});

test('the mailbox mails can be filtered to the unread ones', function () {
    $mailbox = Mailbox::factory()->create();
    IncomingEmail::factory()->for($mailbox)->create(['subject' => 'Olvasott', 'read_at' => now()]);
    IncomingEmail::factory()->for($mailbox)->create(['subject' => 'Olvasatlan', 'read_at' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('mailboxes.show', ['mailbox' => $mailbox, 'read' => 'unread']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('incomingEmails.data', 1)
            ->where('incomingEmails.data.0.subject', 'Olvasatlan')
        );
});

test('the prompt runs can be filtered by status', function () {
    PromptRun::factory()->inContext(['project_id' => $this->project->id])->create(['status' => PromptRunStatus::Failed]);
    PromptRun::factory()->inContext(['project_id' => $this->project->id])->create(['status' => PromptRunStatus::Completed]);

    $this->actingAs(User::factory()->create())
        ->get(route('projects.prompts', ['project' => 'alpha', 'status' => 'failed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('runs.data', 1)
            ->where('runs.data.0.status', 'failed')
        );
});

describe('with elasticsearch', function () {
    beforeEach(function () {
        config(['search.driver' => 'elasticsearch']);
        Http::preventStrayRequests();
    });

    test('saving and deleting a task keeps its document in the index', function () {
        Http::fake([
            'elasticsearch:9200/developer_tool/_doc/*' => Http::response(['result' => 'ok']),
        ]);

        $task = Task::factory()->for($this->project)->create(['title' => 'Indexelt', 'status' => TaskStatus::Todo]);
        $task->delete();

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && str_contains($request->url(), "developer_tool/_doc/task-{$task->id}?require_alias=true")
            && $request['type'] === 'task'
            && $request['title'] === 'Indexelt'
            && $request['project_id'] === $this->project->id
            && $request['status'] === 'todo');

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_contains($request->url(), "developer_tool/_doc/task-{$task->id}"));
    });

    test('the task list is answered by elasticsearch', function () {
        Http::fake(['elasticsearch:9200/developer_tool/_doc/*' => Http::response(['result' => 'ok'])]);
        $first = Task::factory()->for($this->project)->create(['title' => 'Első']);
        $second = Task::factory()->for($this->project)->create(['title' => 'Második']);

        Http::fake(['elasticsearch:9200/developer_tool/_search' => Http::response([
            'hits' => [
                'total' => ['value' => 41],
                'hits' => [
                    ['_source' => ['type' => 'task', 'model' => 'task', 'model_id' => $second->id]],
                    ['_source' => ['type' => 'task', 'model' => 'task', 'model_id' => $first->id]],
                ],
            ],
        ])]);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.tasks.index', ['project' => 'alpha', 'q' => 'feladat', 'status' => 'todo']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.total', 41)
                ->where('tasks.data.0.title', 'Második')
                ->where('tasks.data.1.title', 'Első')
                ->where('search_degraded', false)
            );

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '_search')
            && $request['query']['bool']['filter'] === [
                ['terms' => ['project_id' => [$this->project->id]]],
                ['terms' => ['status' => ['todo']]],
                ['terms' => ['type' => ['task']]],
            ]
            && $request['query']['bool']['must'][0]['bool']['should'][0]['multi_match']['query'] === 'feladat');
    });

    test('an unreachable cluster falls back to the database and is reported', function () {
        Exceptions::fake();
        Http::fake([
            'elasticsearch:9200/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        Task::factory()->for($this->project)->create(['title' => 'Mentett']);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.tasks.index', 'alpha'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('search_degraded', true)
                ->where('tasks.data.0.title', 'Mentett')
            );

        Exceptions::assertReported(SearchUnavailableException::class);
    });

    test('reindexing builds a new index and swaps the alias onto it', function () {
        Task::withoutEvents(fn () => Task::factory()->for($this->project)->create(['title' => 'Régi']));

        Http::fake([
            'elasticsearch:9200/_bulk' => Http::response(['errors' => false, 'items' => []]),
            'elasticsearch:9200/_alias/developer_tool' => Http::response(['developer_tool_old' => ['aliases' => []]]),
            'elasticsearch:9200/_aliases' => Http::response(['acknowledged' => true]),
            'elasticsearch:9200/*' => Http::response(['acknowledged' => true]),
        ]);

        $this->artisan('search:reindex')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '_bulk')
            && str_contains($request->body(), '"title":"Régi"'));

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '_aliases')
            && $request['actions'][1] === ['remove' => ['index' => 'developer_tool_old', 'alias' => 'developer_tool']]
            && str_starts_with($request['actions'][0]['add']['index'], 'developer_tool_'));

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/developer_tool_old'));

        // Elasticsearch rejects a POST _refresh that carries a (JSON) body.
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_ends_with($request->url(), '/_refresh'));
    });
});
