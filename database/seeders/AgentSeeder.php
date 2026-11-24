<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Services\MarkdownFileReader;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Load every Claude Code agent definition from the agents directory into
     * the agents table.
     *
     * Each `*.md` file is read through {@see MarkdownFileReader}: its YAML front
     * matter supplies the name, description, model and tools, and its Markdown
     * body becomes the system prompt. Agents are upserted by name, so re-running
     * the seeder refreshes existing rows instead of duplicating them.
     */
    public function run(MarkdownFileReader $reader): void
    {
        $directory = rtrim((string) config('developer-tool.agents_path'), '/');

        if (! is_dir($directory)) {
            $this->command?->warn("Agents könyvtár nem található: {$directory}");

            return;
        }

        foreach (glob($directory.'/*.md') ?: [] as $path) {
            $document = $reader->read($path);

            $name = $document->matter('name') ?: pathinfo($path, PATHINFO_FILENAME);

            Agent::updateOrCreate(
                ['name' => $name],
                [
                    'description' => $document->matter('description'),
                    'model' => $document->matter('model'),
                    'tools' => Agent::normalizeTools($document->matter('tools')),
                    'system_prompt' => $document->content,
                    'source_path' => $path,
                ],
            );
        }
    }
}
