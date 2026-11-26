<?php

use App\Services\AgentToolService;

test('normalize accepts a comma-separated string', function () {
    expect(AgentToolService::normalize('Read, Grep , Edit'))->toBe(['Read', 'Grep', 'Edit']);
});

test('normalize accepts an array and drops blanks and duplicates', function () {
    expect(AgentToolService::normalize(['Read', ' ', 'Grep', 'Read']))->toBe(['Read', 'Grep']);
});

test('normalize returns null when nothing remains', function () {
    expect(AgentToolService::normalize(''))->toBeNull();
    expect(AgentToolService::normalize([]))->toBeNull();
    expect(AgentToolService::normalize(null))->toBeNull();
});

test('the catalogue names the built-in tools in hungarian', function () {
    $tools = app(AgentToolService::class);

    expect($tools->label('Read'))->toBe('Fájl olvasása');
    expect($tools->label('Bash'))->toBe('Parancs futtatása');
    expect($tools->label('Grep'))->toBe('Keresés a fájlok tartalmában');
});

test('the catalogue lists this applications mcp tools with the hungarian title of the tool class', function () {
    $tools = app(AgentToolService::class);

    expect($tools->label('mcp__tasks__next-task'))->toBe('A következő feladatom');
    expect($tools->label('mcp__mail__reply-email'))->toBe('Válasz egy levélre');
    expect($tools->label('mcp__attachments__attach-to-task'))->toBe('Csatolmány feladathoz kötése');
});

test('the catalogue covers every tool the seeded agents use', function () {
    $names = app(AgentToolService::class)->names();

    $used = collect(glob(base_path('.claude/agents/*.md')))
        ->flatMap(fn (string $path): array => AgentToolService::normalize(
            preg_match('/^tools:(.*)$/m', (string) file_get_contents($path), $matches) ? $matches[1] : null,
        ) ?? [])
        ->unique();

    expect($used)->not->toBeEmpty();
    expect($used->diff($names)->all())->toBe([]);
});

test('an unknown tool keeps its raw name as its label', function () {
    expect(app(AgentToolService::class)->label('mcp__laravel-boost__database-query'))
        ->toBe('mcp__laravel-boost__database-query');
});

test('describe resolves the tools of an agent and flags the uncatalogued ones', function () {
    $described = app(AgentToolService::class)->describe(['Read', 'Nincs-ilyen']);

    expect($described[0])->toMatchArray([
        'name' => 'Read',
        'label' => 'Fájl olvasása',
        'group' => 'Fájlok',
        'known' => true,
    ]);

    expect($described[1])->toMatchArray([
        'name' => 'Nincs-ilyen',
        'label' => 'Nincs-ilyen',
        'description' => null,
        'group' => null,
        'known' => false,
    ]);
});

test('the catalogue marks every group and lists the built-in ones first', function () {
    $kinds = array_map(
        fn (array $group): string => $group['kind'],
        app(AgentToolService::class)->groups(),
    );

    // Claude Code's own tools come first, the server-backed groups after them.
    $servers = array_search(AgentToolService::KIND_BUILTIN, array_reverse($kinds, true), true);

    expect(array_values(array_unique(array_slice($kinds, 0, $servers + 1))))
        ->toBe([AgentToolService::KIND_BUILTIN]);
    expect(array_values(array_unique(array_slice($kinds, $servers + 1))))
        ->toBe([AgentToolService::KIND_MCP, AgentToolService::KIND_MAILBOX]);
});

test('the mail tools are mailbox-driven, so the agent form offers no checkbox for them', function () {
    $mail = array_values(array_filter(
        app(AgentToolService::class)->groups(),
        fn (array $group): bool => $group['kind'] === AgentToolService::KIND_MAILBOX,
    ));

    expect($mail)->toHaveCount(1);
    expect(array_column($mail[0]['tools'], 'name'))->toBe(['mcp__mail__reply-email']);
});

test('only the built-in groups hold tools that are not mcp tools', function () {
    foreach (app(AgentToolService::class)->groups() as $group) {
        foreach ($group['tools'] as $tool) {
            expect(str_starts_with($tool['name'], 'mcp__'))
                ->toBe($group['kind'] !== AgentToolService::KIND_BUILTIN);
        }
    }
});

test('no tool is offered twice, so each one lives on exactly one server', function () {
    $tools = [];

    foreach (app(AgentToolService::class)->groups() as $group) {
        foreach ($group['tools'] as $tool) {
            $tools[] = ['name' => $tool['name'], 'label' => $tool['label'], 'group' => $group['label']];
        }
    }

    $labels = array_column($tools, 'label');
    $duplicated = array_values(array_unique(array_diff_assoc($labels, array_unique($labels))));

    expect(array_column($tools, 'name'))->toHaveCount(count(array_unique(array_column($tools, 'name'))));
    expect($duplicated)->toBe([]);
});

test('every group of the catalogue has tools', function () {
    foreach (app(AgentToolService::class)->groups() as $group) {
        expect($group['tools'])->not->toBeEmpty();
        expect($group['label'])->not->toBeEmpty();
    }
});
