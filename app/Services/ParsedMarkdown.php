<?php

namespace App\Services;

/**
 * The result of reading a Markdown file: its parsed YAML front matter and the
 * Markdown body that follows the closing `---` fence.
 *
 * @see MarkdownFileReader
 */
final readonly class ParsedMarkdown
{
    /**
     * @param  array<string, mixed>  $matter  Parsed front matter (empty when the file has none).
     * @param  string  $content  The Markdown body with the front matter block removed.
     * @param  string|null  $path  Source file path, when the document was read from disk.
     */
    public function __construct(
        public array $matter,
        public string $content,
        public ?string $path = null,
    ) {}

    /**
     * Read a single front matter value, falling back to a default.
     */
    public function matter(string $key, mixed $default = null): mixed
    {
        return $this->matter[$key] ?? $default;
    }

    /**
     * Whether the document carried any front matter.
     */
    public function hasMatter(): bool
    {
        return $this->matter !== [];
    }
}
