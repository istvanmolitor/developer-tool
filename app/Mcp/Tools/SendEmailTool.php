<?php

namespace App\Mcp\Tools;

use App\Enums\OutgoingEmailStatus;
use App\Mcp\Servers\MailServer;
use App\Models\Attachment;
use App\Models\Mailbox;
use App\Repositories\Contracts\AttachmentRepository;
use App\Repositories\Contracts\PromptRunRepository;
use App\Services\MailboxService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('send-email')]
#[Title('Új levél küldése')]
#[Description('Új levél küldése egy postafiókból, amely hozzád van rendelve. Csak a hozzád rendelt postafiókból küldhetsz, más postafiókból nem. A postafiók beállításától függően a levél azonnal kimegy, vagy piszkozatként jóváhagyásra vár. Ez nem válasz egy beérkezett levélre — arra a reply-email eszköz való.')]
class SendEmailTool extends Tool
{
    public function handle(
        Request $request,
        MailboxService $mailboxes,
        PromptRunRepository $promptRuns,
        AttachmentRepository $attachmentRepository,
    ): Response {
        $validated = $request->validate([
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'attachment_ids' => ['nullable', 'array'],
            'attachment_ids.*' => ['integer'],
            'from' => ['nullable', 'string'],
        ]);

        $promptRunId = (int) getenv(MailServer::PROMPT_RUN_ENV);

        if ($promptRunId <= 0) {
            return Response::error('Ez az eszköz csak egy agent-futásból használható.');
        }

        $run = $promptRuns->findWithAgentAndProject($promptRunId);

        if ($run?->agent === null) {
            return Response::error('Ez az agent-futás nem tartozik agenthez, ezért nem küldhetsz levelet.');
        }

        // The mailbox is resolved only from the run's own agent's mailboxes, never
        // from an argument, so an agent can only send from a mailbox assigned to it.
        $mailbox = $this->resolveMailbox($run->agent->mailboxes()->get(), $validated['from'] ?? null);

        if (is_string($mailbox)) {
            return Response::error($mailbox);
        }

        $attachmentIds = array_values(array_unique($validated['attachment_ids'] ?? []));
        $related = $attachmentRepository->byIds($attachmentIds);

        if (count($related) !== count($attachmentIds)) {
            $missing = array_diff($attachmentIds, $related->pluck('id')->all());

            return Response::error('Nincs ilyen azonosítójú csatolmány: '.implode(', ', $missing).'. Használd a list-attachments eszközt a létező csatolmányokhoz.');
        }

        // A file belonging to another project must not be sent from this mailbox.
        $foreign = $related->filter(
            fn (Attachment $attachment): bool => $attachment->project_id !== null && $attachment->project_id !== $mailbox->project_id,
        );

        if ($foreign->isNotEmpty()) {
            return Response::error('A(z) '.$foreign->pluck('id')->join(', ')." csatolmány másik projekthez tartozik, nem küldhető a(z) „{$mailbox->name}” postafiókból.");
        }

        $outgoing = $mailboxes->compose(
            $mailbox,
            $validated['to'],
            $validated['subject'],
            $validated['body'],
            $attachmentIds,
            $run,
            autoSend: $mailbox->auto_reply,
        );

        $recipients = implode(', ', $outgoing->to);

        return match ($outgoing->status) {
            OutgoingEmailStatus::Sent => Response::text("A levél elküldve a(z) {$mailbox->email} címről: {$recipients}."),
            OutgoingEmailStatus::Draft => Response::text('A levél piszkozatként elmentve; egy felhasználó hagyja jóvá a küldését.'),
            OutgoingEmailStatus::Failed => Response::error("A levél elmentve, de a küldés nem sikerült: {$outgoing->error}"),
        };
    }

    /**
     * Pick the mailbox to send from among the agent's own mailboxes. With one
     * mailbox it is used directly; with several the `from` address selects it.
     *
     * @param  Collection<int, Mailbox>  $mailboxes
     * @return Mailbox|string The mailbox, or an error message.
     */
    private function resolveMailbox(Collection $mailboxes, ?string $from): Mailbox|string
    {
        if ($mailboxes->isEmpty()) {
            return 'Nincs hozzád rendelve postafiók, ezért nem küldhetsz levelet.';
        }

        if ($from === null || trim($from) === '') {
            if ($mailboxes->count() > 1) {
                return 'Több postafiók is hozzád van rendelve, add meg a `from` mezőben, melyikből küldesz: '.$mailboxes->pluck('email')->join(', ').'.';
            }

            return $mailboxes->first();
        }

        $match = $mailboxes->first(
            fn (Mailbox $mailbox): bool => strcasecmp($mailbox->email, trim($from)) === 0,
        );

        if ($match === null) {
            return "A(z) „{$from}” nem a hozzád rendelt postafiókok egyike. Csak ezekből küldhetsz: ".$mailboxes->pluck('email')->join(', ').'.';
        }

        return $match;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->array()
                ->description('A címzettek e-mail címei.')
                ->items($schema->string())
                ->required(),
            'subject' => $schema->string()
                ->description('A levél tárgya.')
                ->required(),
            'body' => $schema->string()
                ->description('A levél szövege (sima szöveg).')
                ->required(),
            'attachment_ids' => $schema->array()
                ->description('Opcionális: a levélhez csatolandó, már létező csatolmányok azonosítói. Az azonosítókat a list-attachments vagy a create-attachment adja. Minden csatolmánynak a postafiók projektjéhez (vagy egyik projekthez sem) kell tartoznia.')
                ->items($schema->integer()),
            'from' => $schema->string()
                ->description('Csak ha több postafiók van hozzád rendelve: annak a postafióknak az e-mail címe, amelyikből küldeni szeretnél.'),
        ];
    }
}
