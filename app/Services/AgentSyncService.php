<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps the Claude Code agent definitions (`*.md` files in the agents directory)
 * and the agents table in sync, in both directions: {@see sync()} loads the
 * files into the table, {@see writeFile()} writes an agent back out to its file
 * so edits made in the admin UI land in the definition files too.
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

    /**
     * Write an agent back out to its definition file (`<slug>.md` in the agents
     * directory), so changes made in the admin UI are reflected in the files the
     * {@see sync()} reads. The file is a round-trip of what sync parses: the slug
     * is the file name, the readable name goes to the `title` front matter, and
     * the system prompt becomes the Markdown body.
     *
     * When the slug changed (an agent was renamed), the file under the previous
     * slug is removed so no stale duplicate is left behind.
     *
     * @return string The absolute path of the file written.
     */
    public function writeFile(Agent $agent, ?string $previousSlug = null): string
    {
        $directory = $this->agentsPath();

        File::ensureDirectoryExists($directory);

        if ($previousSlug !== null && $previousSlug !== '' && $previousSlug !== $agent->slug) {
            $this->deleteFileForSlug($previousSlug);
        }

        $path = $directory.'/'.$agent->slug.'.md';

        File::put($path, $this->toMarkdown($agent));

        return $path;
    }

    /**
     * Remove an agent's definition file, so deleting an agent in the admin UI
     * does not leave a file behind that the next {@see sync()} would re-import.
     */
    public function deleteFile(Agent $agent): void
    {
        $this->deleteFileForSlug($agent->slug);
    }

    /**
     * Delete the definition file of the given slug when it exists.
     */
    private function deleteFileForSlug(string $slug): void
    {
        $path = $this->agentsPath().'/'.$slug.'.md';

        if (File::isFile($path)) {
            File::delete($path);
        }
    }

    /**
     * Serialize an agent into the `*.md` definition format: YAML front matter
     * (name/title/description/model/tools) followed by the system prompt. Tools
     * are written as the comma-separated string the files and the form use.
     */
    private function toMarkdown(Agent $agent): string
    {
        $tools = Agent::normalizeTools($agent->tools);

        $matter = array_filter([
            'name' => $agent->slug,
            'title' => $agent->name,
            'description' => $agent->description,
            'model' => $agent->model,
            'tools' => $tools === null ? null : implode(', ', $tools),
        ], fn ($value): bool => $value !== null && $value !== '');

        $frontMatter = Yaml::dump($matter, 2, 2);

        $body = trim((string) $agent->system_prompt);

        return "---\n{$frontMatter}---\n\n{$body}\n";
    }
}
