<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesMessagingContext;
use App\Models\AgentMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-messages')]
#[Title('Beérkezett üzenetek')]
#[Description('A neked (az aktuális agentnek) a projektben érkezett üzenetek időrendben, legrégebbi elöl. Alapértelmezetten csak az olvasatlanokat adja vissza, és a visszaadott üzeneteket olvasottnak jelöli.')]
class ListMessagesTool extends Tool
{
    use ResolvesMessagingContext;

    /**
     * The most messages returned by one call.
     */
    private const LIMIT = 50;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'unread_only' => ['nullable', 'boolean'],
            'project' => ['nullable', 'string'],
            'agent' => ['nullable', 'string'],
        ]);

        $context = $this->resolveMessagingContext($validated);

        if (is_string($context)) {
            return Response::error($context);
        }

        [$recipient, $project] = $context;
        $unreadOnly = $validated['unread_only'] ?? true;

        $messages = AgentMessage::query()
            ->with(['sender:id,name', 'recipient:id,name'])
            ->whereBelongsTo($project)
            ->where('recipient_agent_id', $recipient->id)
            ->when($unreadOnly, fn ($query) => $query->unread())
            ->when(
                $unreadOnly,
                fn ($query) => $query->oldest('id'),
                fn ($query) => $query->latest('id'),
            )
            ->limit(self::LIMIT)
            ->get()
            ->sortBy('id')
            ->values();

        // Shape the messages before marking them, so the response shows which ones were new.
        $display = $messages->map(fn (AgentMessage $message): array => $message->toDisplayArray())->all();

        AgentMessage::query()
            ->whereKey($messages->modelKeys())
            ->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        return Response::json([
            'project' => $project->name,
            'agent' => $recipient->name,
            'count' => count($display),
            'remaining_unread' => AgentMessage::query()
                ->whereBelongsTo($project)
                ->where('recipient_agent_id', $recipient->id)
                ->unread()
                ->count(),
            'messages' => $display,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'unread_only' => $schema->boolean()
                ->description('Csak az olvasatlan üzenetek (alapértelmezett: true). false esetén a legutóbbi 50 üzenet olvasottal együtt.'),
            ...$this->messagingContextSchema($schema),
        ];
    }
}
