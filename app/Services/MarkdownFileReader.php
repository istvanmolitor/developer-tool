<?php

namespace App\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * Reads a Markdown file and splits it into YAML front matter and body.
 *
 * Front matter is the optional block fenced by `---` lines at the very start of
 * the file (the same format used by the Claude Code agent definitions under
 * `.claude/agents`). Files without front matter are returned with an empty
 * matter array and the whole file as content.
 */
class MarkdownFileReader
{
    /**
     * Read and parse a Markdown file from disk.
     *
     * @throws \RuntimeException when the file does not exist or cannot be read.
     */
    public function read(string $path): ParsedMarkdown
    {
        if (! is_file($path)) {
            throw new \RuntimeException("A Markdown fájl nem található: {$path}");
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new \RuntimeException("A Markdown fájl nem olvasható: {$path}");
        }

        return $this->parse($raw, $path);
    }

    /**
     * Parse a raw Markdown string into front matter and body.
     */
    public function parse(string $raw, ?string $path = null): ParsedMarkdown
    {
        // Strip a UTF-8 byte order mark so the opening fence is at offset 0.
        $raw = preg_replace('/\A\xEF\xBB\xBF/', '', $raw) ?? $raw;

        $matter = [];
        $content = $raw;

        // Front matter must open the file: `---`, the YAML, then a closing `---`.
        if (preg_match('/\A---[ \t]*\R(.*?)\R---[ \t]*(?:\R|\z)/s', $raw, $match)) {
            $parsed = Yaml::parse($match[1]);
            $matter = is_array($parsed) ? $parsed : [];
            $content = substr($raw, strlen($match[0]));
        }

        return new ParsedMarkdown($matter, trim($content), $path);
    }
}
