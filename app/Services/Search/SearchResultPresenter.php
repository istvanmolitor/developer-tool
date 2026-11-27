<?php

namespace App\Services\Search;

use App\Enums\SearchType;
use App\Repositories\Contracts\ProjectRepository;

/**
 * Turns search hits into display rows for the global search: label, title,
 * highlighted snippet and the URL of the page showing the item.
 */
class SearchResultPresenter
{
    public function __construct(private ProjectRepository $projects) {}

    /**
     * @return list<array{key: string, type: string, type_label: string, title: string, title_html: string, snippet: string|null, url: string|null, project: string|null, created_at: string|null}>
     */
    public function present(SearchResults $results): array
    {
        $projectNames = collect($this->projects->namesByIds(
            array_filter(array_map(fn (SearchHit $hit): mixed => $hit->document['project_id'] ?? null, $results->hits)),
        ));

        return array_map(function (SearchHit $hit) use ($projectNames): array {
            $projectName = $projectNames->get($hit->document['project_id'] ?? null);

            return [
                'key' => $hit->model.'-'.$hit->modelId,
                'type' => $hit->type->value,
                'type_label' => $hit->type->label(),
                'title' => (string) ($hit->document['title'] ?? ''),
                'title_html' => $hit->titleHighlight ?? e((string) ($hit->document['title'] ?? '')),
                'snippet' => $hit->snippet,
                'url' => $this->url($hit, $projectName),
                'project' => $projectName,
                'created_at' => $hit->document['created_at'] ?? null,
            ];
        }, $results->hits);
    }

    /**
     * The page showing the hit, or null when its project is gone.
     */
    private function url(SearchHit $hit, ?string $projectName): ?string
    {
        $document = $hit->document;

        return match ($hit->type) {
            SearchType::Task => $projectName === null ? null
                : route('projects.tasks.show', [$projectName, $hit->modelId], false),
            SearchType::Document => $projectName === null ? null
                : route('projects.documents.show', [$projectName, $hit->modelId], false),
            SearchType::Prompt => $projectName === null ? null
                : route('projects.prompts', ['project' => $projectName, 'q' => mb_substr((string) ($document['title'] ?? ''), 0, 60)], false),
            SearchType::Comment => $projectName === null || empty($document['task_id']) ? null
                : route('projects.tasks.show', [$projectName, $document['task_id']], false).'#comment-'.$hit->modelId,
            SearchType::Mail => empty($document['mailbox_id']) ? null
                : route('mailboxes.show', $document['mailbox_id'], false).'#email-'.($document['incoming_email_id'] ?? $hit->modelId),
            SearchType::Message => $this->messageUrl($document),
        };
    }

    /**
     * Open the conversation from the recipient's side (the sender's, when the
     * recipient is gone).
     *
     * @param  array<string, mixed>  $document
     */
    private function messageUrl(array $document): ?string
    {
        $agentId = $document['recipient_agent_id'] ?? $document['sender_agent_id'] ?? null;

        if ($agentId === null) {
            return null;
        }

        $partnerId = $agentId === ($document['recipient_agent_id'] ?? null)
            ? ($document['sender_agent_id'] ?? null)
            : ($document['recipient_agent_id'] ?? null);

        return route('agents.messages', array_filter([
            'agent' => $agentId,
            'project' => $document['project_id'] ?? null,
            'partner' => $partnerId,
        ]), false);
    }
}
