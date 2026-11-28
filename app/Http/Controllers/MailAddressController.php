<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMailAddressRequest;
use App\Http\Requests\UpdateMailAddressRequest;
use App\Models\IncomingEmail;
use App\Models\MailAddress;
use App\Models\OutgoingEmail;
use App\Repositories\Contracts\MailAddressRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MailAddressController extends Controller
{
    /**
     * The list columns the list may be ordered by: request value => column.
     */
    private const SORTS = [
        'mail' => 'mail',
        'name' => 'name',
        'is_spam' => 'is_spam',
        'created_at' => 'created_at',
    ];

    public function __construct(private MailAddressRepository $mailAddresses) {}

    /**
     * The address list: free-text filtered (address or name), ordered by a
     * whitelisted column in a chosen direction, paginated.
     */
    public function index(Request $request): Response
    {
        $sort = array_key_exists($request->string('sort')->toString(), self::SORTS)
            ? $request->string('sort')->toString()
            : 'mail';

        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $filters = [
            'q' => trim($request->string('q')->toString()),
            'sort' => $sort,
            'direction' => $direction,
        ];

        return Inertia::render('MailAddresses/Index', [
            'mailAddresses' => $this->mailAddresses->paginateForIndex(
                $filters['q'],
                self::SORTS[$sort],
                $direction,
                25,
            ),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('MailAddresses/Create');
    }

    public function store(StoreMailAddressRequest $request): RedirectResponse
    {
        $this->mailAddresses->create([
            'mail' => $request->validated('mail'),
            'name' => $request->validated('name'),
            'is_spam' => $request->boolean('is_spam'),
        ]);

        return redirect()
            ->route('mail-addresses.index')
            ->with('status', 'E-mail cím hozzáadva.');
    }

    /**
     * The address with its conversation: the messages received from it and the
     * messages sent to it, across every mailbox, oldest first like a chat.
     */
    public function show(MailAddress $mailAddress): Response
    {
        $mail = $mailAddress->mail;

        $incoming = IncomingEmail::query()
            ->whereRaw('lower(from_address) = ?', [$mail])
            ->with('mailbox:id,name,email')
            ->get(['id', 'mailbox_id', 'from_address', 'from_name', 'subject', 'body_text', 'received_at', 'created_at'])
            ->map(fn (IncomingEmail $email): array => [
                'id' => "in-{$email->id}",
                'direction' => 'incoming',
                'subject' => $email->subject,
                'body' => $email->body_text,
                'status' => null,
                'mailbox' => $email->mailbox?->only(['id', 'name', 'email']),
                'date' => ($email->received_at ?? $email->created_at)?->toIso8601String(),
            ]);

        $outgoing = OutgoingEmail::query()
            ->whereJsonContains('to', $mail)
            ->with('mailbox:id,name,email')
            ->get(['id', 'mailbox_id', 'to', 'subject', 'body_text', 'status', 'sent_at', 'created_at'])
            ->map(fn (OutgoingEmail $email): array => [
                'id' => "out-{$email->id}",
                'direction' => 'outgoing',
                'subject' => $email->subject,
                'body' => $email->body_text,
                'status' => $email->status->value,
                'mailbox' => $email->mailbox?->only(['id', 'name', 'email']),
                'date' => ($email->sent_at ?? $email->created_at)?->toIso8601String(),
            ]);

        return Inertia::render('MailAddresses/Show', [
            'mailAddress' => $mailAddress,
            // ISO-8601 timestamps sort chronologically as plain strings.
            'conversation' => $incoming->concat($outgoing)->sortBy('date')->values(),
        ]);
    }

    public function edit(MailAddress $mailAddress): Response
    {
        return Inertia::render('MailAddresses/Edit', [
            'mailAddress' => $mailAddress,
        ]);
    }

    public function update(UpdateMailAddressRequest $request, MailAddress $mailAddress): RedirectResponse
    {
        $this->mailAddresses->update($mailAddress, [
            'mail' => $request->validated('mail'),
            'name' => $request->validated('name'),
            'is_spam' => $request->boolean('is_spam'),
        ]);

        return redirect()
            ->route('mail-addresses.show', $mailAddress)
            ->with('status', 'E-mail cím frissítve.');
    }

    public function destroy(MailAddress $mailAddress): RedirectResponse
    {
        $this->mailAddresses->delete($mailAddress);

        return redirect()
            ->route('mail-addresses.index')
            ->with('status', 'E-mail cím törölve.');
    }
}
