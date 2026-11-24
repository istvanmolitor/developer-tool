<?php

namespace App\Http\Controllers;

use App\Enums\MailEncryption;
use App\Http\Requests\StoreMailboxRequest;
use App\Http\Requests\UpdateMailboxRequest;
use App\Models\Agent;
use App\Models\Mailbox;
use App\Models\Project;
use App\Services\MailboxService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class MailboxController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Mailboxes/Index', [
            'mailboxes' => Mailbox::query()
                ->with(['agent:id,name', 'project:id,name'])
                ->withCount([
                    'incomingEmails',
                    'incomingEmails as unread_emails_count' => fn (Builder $query) => $query->whereNull('read_at'),
                ])
                ->orderBy('name')
                ->get(),
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
        Mailbox::create($request->validated());

        return redirect()
            ->route('mailboxes.index')
            ->with('status', 'Postafiók létrehozva.');
    }

    public function show(Mailbox $mailbox): Response
    {
        return Inertia::render('Mailboxes/Show', [
            'mailbox' => $mailbox->load(['agent:id,name', 'project:id,name']),
            'incomingEmails' => $mailbox->incomingEmails()
                ->with([
                    'attachments:id,incoming_email_id,filename,mime_type,size',
                    'promptRun:id,status',
                    'replies:id,incoming_email_id,to,subject,body_text,status,error,sent_at,created_at',
                ])
                ->latest('received_at')
                ->latest('id')
                ->limit(50)
                ->get(['id', 'mailbox_id', 'from_address', 'from_name', 'subject', 'body_text', 'received_at', 'read_at', 'prompt_run_id']),
            'incomingEmailsCount' => $mailbox->incomingEmails()->count(),
            'unreadEmailsCount' => $mailbox->incomingEmails()->whereNull('read_at')->count(),
        ]);
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

        $mailbox->update($data);

        return redirect()
            ->route('mailboxes.show', $mailbox)
            ->with('status', 'Postafiók frissítve.');
    }

    public function destroy(Mailbox $mailbox): RedirectResponse
    {
        $mailbox->delete();

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
     * @return list<array{id: int, name: string}>
     */
    private function agentOptions(): array
    {
        $options = [];

        foreach (Agent::query()->orderBy('name')->get(['id', 'name']) as $agent) {
            $options[] = ['id' => $agent->id, 'name' => $agent->name];
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

        foreach (Project::query()->orderBy('name')->get(['id', 'name']) as $project) {
            $options[] = ['id' => $project->id, 'name' => $project->name];
        }

        return $options;
    }
}
