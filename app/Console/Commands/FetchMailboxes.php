<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use App\Services\MailboxService;
use Illuminate\Console\Command;
use RuntimeException;

class FetchMailboxes extends Command
{
    protected $signature = 'mailboxes:fetch {mailbox? : A postafiók neve vagy azonosítója (alapértelmezés: minden aktív postafiók)}';

    protected $description = 'Új levelek letöltése a postafiókok IMAP szerveréről.';

    public function handle(MailboxService $mailboxService): int
    {
        $identifier = $this->argument('mailbox');

        $mailboxes = Mailbox::query()
            ->when(
                $identifier !== null,
                fn ($query) => $query->where('name', $identifier)->orWhere('id', $identifier),
                fn ($query) => $query->where('is_active', true),
            )
            ->orderBy('name')
            ->get();

        if ($mailboxes->isEmpty()) {
            $this->warn('Nincs letölthető postafiók.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($mailboxes as $mailbox) {
            try {
                $created = $mailboxService->fetchIncoming($mailbox);
                $this->info("{$mailbox->name}: {$created} új levél.");
            } catch (RuntimeException $exception) {
                report($exception);
                $this->error($exception->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
