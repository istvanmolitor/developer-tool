<?php

namespace App\Mcp\Tools;

use App\Enums\OutgoingEmailStatus;
use App\Mcp\Servers\MailServer;
use App\Repositories\Contracts\IncomingEmailRepository;
use App\Services\MailboxService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Name('reply-email')]
#[Title('Válasz egy levélre')]
#[Description('Válasz a feldolgozásra kapott levél feladójának, abból a postafiókból, amelybe a levél érkezett. Levelenként egyszer használható. A postafiók beállításától függően a válasz azonnal kimegy, vagy piszkozatként jóváhagyásra vár.')]
class ReplyEmailTool extends Tool
{
    public function handle(Request $request, MailboxService $mailboxes, IncomingEmailRepository $incomingEmails): Response
    {
        $validated = $request->validate([
            'incoming_email_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:50000'],
        ]);

        $promptRunId = (int) getenv(MailServer::PROMPT_RUN_ENV);

        if ($promptRunId <= 0) {
            return Response::error('Ez az eszköz csak egy levelet feldolgozó agent-futásból használható.');
        }

        // Only the message handed to this run can be answered, so an agent cannot write from another mailbox.
        $incomingEmail = $incomingEmails->findForPromptRun($validated['incoming_email_id'], $promptRunId);

        if ($incomingEmail === null) {
            return Response::error("A(z) {$validated['incoming_email_id']} azonosítójú levelet nem ebben a futásban kaptad, ezért nem válaszolhatsz rá.");
        }

        try {
            $reply = $mailboxes->reply($incomingEmail, $validated['body'], $incomingEmail->promptRun);
        } catch (RuntimeException $exception) {
            return Response::error($exception->getMessage());
        }

        return match ($reply->status) {
            OutgoingEmailStatus::Sent => Response::text('A válasz elküldve: '.implode(', ', $reply->to).'.'),
            OutgoingEmailStatus::Draft => Response::text('A válasz piszkozatként elmentve; egy felhasználó hagyja jóvá a küldését.'),
            OutgoingEmailStatus::Failed => Response::error("A válasz elmentve, de a küldés nem sikerült: {$reply->error}"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'incoming_email_id' => $schema->integer()
                ->description('A megválaszolandó levél azonosítója, ahogy a prompt megadta.')
                ->required(),
            'body' => $schema->string()
                ->description('A válasz szövege (sima szöveg). A tárgy és a címzett automatikusan az eredeti levélből jön.')
                ->required(),
        ];
    }
}
