<?php

namespace App\Http\Controllers;

use App\Enums\MailEncryption;
use App\Enums\SearchType;
use App\Http\Requests\StoreMailboxRequest;
use App\Http\Requests\UpdateMailboxRequest;
use App\Models\IncomingEmail;
use App\Models\MailAddress;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use App\Repositories\Contracts\AgentRepository;
use App\Repositories\Contracts\MailboxRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Services\MailboxService;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class MailboxController extends Controller
{
    public function __construct(
        private MailboxRepository $mailboxes,
        private AgentRepository $agents,
        private ProjectRepository $projects,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Mailboxes/Index', [
            'mailboxes' => $this->mailboxes->allForIndex(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Mailboxes/Create', [
            'encryptions' => $this->encryptionOptions(),
            'agents' => $this->agentOptions(),
            'projects' => $this->projectOptions(),
        ]);
    }

    public function store(StoreMailboxRequest $request): RedirectResponse
    {
        $this->mailboxes->create($request->validated());

        return redirect()
            ->route('mailboxes.index')
            ->with('status', 'Postafiók létrehozva.');
    }

    /**
     * The mailbox's connection details: its agent, project, IMAP/SMTP settings and
     * mail counts. The mail lists live on their own pages.
     */
    public function show(Mailbox $mailbox): Response
    {
        return Inertia::render('Mailboxes/Show', [
            'mailbox' => $mailbox->load(['agent:id,name', 'project:id,name']),
            ...$this->mailCounts($mailbox),
        ]);
    }

    /**
     * The mailbox's received mail from the search index, newest first, filterable
     * by text (subject, body, sender) and read state, and paginated.
     */
    public function incoming(Request $request, Mailbox $mailbox, SearchService $search): Response
    {
        $readState = $request->string('read')->toString();

        $filters = [
            'q' => trim($request->string('q')->toString()),
            'read' => in_array($readState, ['read', 'unread'], true) ? $readState : null,
        ];

        $results = $search->search(
            SearchCriteria::for(SearchType::Mail)
                ->matching($filters['q'])
                ->where('mailbox_id', $mailbox->id)
                ->where('direction', 'incoming')
                ->where('is_read', $filters['read'] === null ? null : $filters['read'] === 'read')
                ->orderBy('created_at', 'desc')
                ->paginate($request->integer('page', 1), 25),
        );

        $incomingEmails = $results->paginate(
            IncomingEmail::query()
                ->select(['id', 'mailbox_id', 'from_address', 'from_name', 'subject', 'body_text', 'received_at', 'read_at', 'prompt_run_id'])
                ->with([
                    'attachments:id,filename,mime_type,size',
                    'promptRun:id,status',
                    'replies:id,incoming_email_id,to,subject,body_text,status,error,sent_at,created_at',
                ]),
            $request,
        );

        return Inertia::render('Mailboxes/Incoming', [
            'mailbox' => $mailbox->load(['agent:id,name', 'project:id,name']),
            'incomingEmails' => $incomingEmails,
            'mailAddressIds' => $this->mailAddressIds(
                collect($incomingEmails->items())->pluck('from_address')->all(),
            ),
            'filters' => $filters,
            'search_degraded' => $results->degraded,
            ...$this->mailCounts($mailbox),
        ]);
    }

    /**
     * The mailbox's outgoing mail (sent and drafts) from the search index, newest
     * first, filterable by text and paginated.
     */
    public function outgoing(Request $request, Mailbox $mailbox, SearchService $search): Response
    {
        $filters = ['q' => trim($request->string('q')->toString())];

        $results = $search->search(
            SearchCriteria::for(SearchType::Mail)
                ->matching($filters['q'])
                ->where('mailbox_id', $mailbox->id)
                ->where('direction', 'outgoing')
                ->orderBy('created_at', 'desc')
                ->paginate($request->integer('page', 1), 25),
        );

        $outgoingEmails = $results->paginate(
            OutgoingEmail::query()
                ->select(['id', 'mailbox_id', 'incoming_email_id', 'to', 'subject', 'body_text', 'status', 'error', 'sent_at', 'created_at'])
                ->with(['attachments:id,filename,mime_type,size']),
            $request,
        );

        return Inertia::render('Mailboxes/Outgoing', [
            'mailbox' => $mailbox->load(['agent:id,name', 'project:id,name']),
            'outgoingEmails' => $outgoingEmails,
            'mailAddressIds' => $this->mailAddressIds(
                collect($outgoingEmails->items())->flatMap(fn (OutgoingEmail $email): array => $email->to ?? [])->all(),
            ),
            'filters' => $filters,
            'search_degraded' => $results->degraded,
            ...$this->mailCounts($mailbox),
        ]);
    }

    /**
     * The mail counts shared by the connection page and the two list pages, for the
     * navigation between them.
     *
     * @return array{incomingEmailsCount: int, unreadEmailsCount: int, outgoingEmailsCount: int}
     */
    private function mailCounts(Mailbox $mailbox): array
    {
        return [
            'incomingEmailsCount' => $mailbox->incomingEmails()->count(),
            'unreadEmailsCount' => $mailbox->incomingEmails()->whereNull('read_at')->count(),
            'outgoingEmailsCount' => $mailbox->outgoingEmails()->count(),
        ];
    }

    /**
     * Map the given addresses (lowercased) to their mail_addresses id, so each mail
     * row can link to the conversation with that address.
     *
     * @param  array<array-key, mixed>  $addresses
     * @return array<string, int>
     */
    private function mailAddressIds(array $addresses): array
    {
        $addresses = collect($addresses)
            ->filter(fn ($address): bool => is_string($address) && $address !== '')
            ->map(fn (string $address): string => mb_strtolower(trim($address)))
            ->unique()
            ->values()
            ->all();

        if ($addresses === []) {
            return [];
        }

        return MailAddress::query()
            ->whereIn('mail', $addresses)
            ->pluck('id', 'mail')
            ->all();
    }

    public function edit(Mailbox $mailbox): Response
    {
        return Inertia::render('Mailboxes/Edit', [
            'mailbox' => $mailbox,
            'encryptions' => $this->encryptionOptions(),
            'agents' => $this->agentOptions(),
            'projects' => $this->projectOptions(),
        ]);
    }

    public function update(UpdateMailboxRequest $request, Mailbox $mailbox): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $this->mailboxes->update($mailbox, $data);

        return redirect()
            ->route('mailboxes.show', $mailbox)
            ->with('status', 'Postafiók frissítve.');
    }

    public function destroy(Mailbox $mailbox): RedirectResponse
    {
        $this->mailboxes->delete($mailbox);

        return redirect()
            ->route('mailboxes.index')
            ->with('status', 'Postafiók eltávolítva.');
    }

    /**
     * Download the new messages of the mailbox from its IMAP server.
     */
    public function fetch(Mailbox $mailbox, MailboxService $mailboxes): RedirectResponse
    {
        try {
            $created = $mailboxes->fetchIncoming($mailbox);

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$created} új levél letöltve."]);
        } catch (RuntimeException $exception) {
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect()->route('mailboxes.show', $mailbox);
    }

    /**
     * Encryption values with labels for the frontend selects.
     *
     * @return list<array{value: string, label: string}>
     */
    private function encryptionOptions(): array
    {
        return array_map(
            fn (MailEncryption $encryption): array => ['value' => $encryption->value, 'label' => $encryption->label()],
            MailEncryption::cases(),
        );
    }

    /**
     * Agents a mailbox can be assigned to, for the form's owner select.
     *
     * @return list<array{id: int, name: string, avatar_url: string|null}>
     */
    private function agentOptions(): array
    {
        $options = [];

        foreach ($this->agents->allOrderedByName(['id', 'name', 'avatar_path', 'updated_at']) as $agent) {
            $options[] = ['id' => $agent->id, 'name' => $agent->name, 'avatar_url' => $agent->avatar_url];
        }

        return $options;
    }

    /**
     * Projects the mailbox's agent can work on, for the form's project select.
     *
     * @return list<array{id: int, name: string}>
     */
    private function projectOptions(): array
    {
        $options = [];

        foreach ($this->projects->allOrderedByName(['id', 'name']) as $project) {
            $options[] = ['id' => $project->id, 'name' => $project->name];
        }

        return $options;
    }
}
