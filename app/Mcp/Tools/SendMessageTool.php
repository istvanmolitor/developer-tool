<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesMessagingContext;
use App\Repositories\Contracts\AgentMessageRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('send-message')]
#[Title('Üzenet küldése agentnek')]
#[Description('Üzenet küldése egy másik agentnek ugyanazon a projekten belül. A címzett a list-messages eszközzel olvassa el.')]
class SendMessageTool extends Tool
{
    use ResolvesMessagingContext;

    public function __construct(private AgentMessageRepository $messages) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'to' => ['required', 'string'],
            'body' => ['required', 'string', 'max:20000'],
            'project' => ['nullable', 'string'],
            'agent' => ['nullable', 'string'],
        ]);

        $context = $this->resolveMessagingContext($validated);

        if (is_string($context)) {
            return Response::error($context);
        }

        [$sender, $project] = $context;

        $recipient = $this->resolveAgent($validated['to']);

        if ($recipient === null) {
            return Response::error("Nincs „{$validated['to']}” nevű agent. Használd a list-agents eszközt a nevekhez.");
        }

        if ($recipient->is($sender)) {
            return Response::error('Saját magadnak nem küldhetsz üzenetet.');
        }

        $message = $this->messages->create([
            'project_id' => $project->id,
            'sender_agent_id' => $sender->id,
            'recipient_agent_id' => $recipient->id,
            'body' => $validated['body'],
        ]);

        return Response::json([
            'message' => "Üzenet elküldve neki: {$recipient->name}.",
            'project' => $project->name,
            'sent' => $message->setRelation('sender', $sender)->setRelation('recipient', $recipient)->toDisplayArray(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->string()
                ->description('A címzett agent neve, slug-ja vagy azonosítója.')
                ->required(),
            'body' => $schema->string()
                ->description('Az üzenet szövege.')
                ->required(),
            ...$this->messagingContextSchema($schema),
        ];
    }
}
