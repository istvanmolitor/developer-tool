<?php

use App\Services\MarkdownFileReader;

beforeEach(function () {
    $this->reader = new MarkdownFileReader;
});

test('it splits front matter from the body', function () {
    $parsed = $this->reader->parse(<<<'MD'
        ---
        name: documentarian
        model: claude-sonnet-5
        tools: Read, Grep, Edit
        ---

        # Heading

        Body text.
        MD);

    expect($parsed->matter('name'))->toBe('documentarian')
        ->and($parsed->matter('model'))->toBe('claude-sonnet-5')
        ->and($parsed->matter('tools'))->toBe('Read, Grep, Edit')
        ->and($parsed->content)->toBe("# Heading\n\nBody text.");
});

test('a document without front matter keeps the whole file as content', function () {
    $parsed = $this->reader->parse("# Just Markdown\n\nNo front matter here.");

    expect($parsed->hasMatter())->toBeFalse()
        ->and($parsed->matter)->toBe([])
        ->and($parsed->content)->toBe("# Just Markdown\n\nNo front matter here.");
});

test('matter() returns the default for a missing key', function () {
    $parsed = $this->reader->parse("---\nname: x\n---\nbody");

    expect($parsed->matter('model', 'claude-opus-4-8'))->toBe('claude-opus-4-8');
});

test('it reads a file from disk', function () {
    $path = sys_get_temp_dir().'/md-reader-'.uniqid().'.md';
    file_put_contents($path, "---\nname: from-disk\n---\nHello");

    $parsed = $this->reader->read($path);

    expect($parsed->matter('name'))->toBe('from-disk')
        ->and($parsed->content)->toBe('Hello')
        ->and($parsed->path)->toBe($path);

    unlink($path);
});

test('reading a missing file throws', function () {
    expect(fn () => $this->reader->read('/no/such/file.md'))
        ->toThrow(RuntimeException::class);
});
