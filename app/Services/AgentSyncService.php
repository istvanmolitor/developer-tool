<?php

namespace App\Services;

use App\Models\Agent;
use RuntimeException;

/**
 * Synchronizes the Claude Code agent definitions (`*.md` files in the agents
 * directory) into the agents table.
 */
class AgentSyncService
{
    public function __construct(public MarkdownFileReader $reader) {}

    /**
     * Absolute path to the directory holding the agent definition files.
     */
    public function agentsPath(): string
    {
        return rtrim((string) config('developer-tool.agents_path'), '/');
    }

    /**
     * Load every agent definition file into the agents table.
     *
     * The file's name (an English slug, e.g. `database-expert.md`) is the
     * agent's machine identifier; its YAML front matter supplies the readable
     * `title`, the description, model and tools, and its Markdown body becomes
     * the system prompt. Agents are upserted by slug, so re-running refreshes
     * existing rows instead of duplicating them.
     *
     * @return int The number of agent files synchronized.
     *
     * @throws RuntimeException when the agents directory does not exist.
     */
    public function sync(): int
    {
        $directory = $this->agentsPath();

        if (! is_dir($directory)) {
            throw new RuntimeException("Agents könyvtár nem található: {$directory}");
        }

        $paths = glob($directory.'/*.md') ?: [];

        foreach ($paths as $path) {
            $document = $this->reader->read($path);

            $slug = pathinfo($path, PATHINFO_FILENAME);

            Agent::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $document->matter('title') ?: ($document->matter('name') ?: $slug),
                    'description' => $document->matter('description'),
                    'model' => $document->matter('model'),
                    'tools' => Agent::normalizeTools($document->matter('tools')),
                    'system_prompt' => $document->content,
                    'source_path' => $path,
                ],
            );
        }

        return count($paths);
    }
}
