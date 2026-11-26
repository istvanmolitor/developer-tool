<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\MailboxRepository;
use App\Services\MailboxService;
use Illuminate\Console\Command;
use RuntimeException;

class FetchMailboxes extends Command
{
    protected $signature = 'mailboxes:fetch {mailbox? : A postafiók neve vagy azonosítója (alapértelmezés: minden aktív postafiók)}';

    protected $description = 'Új levelek letöltése a postafiókok IMAP szerveréről.';

    public function handle(MailboxService $mailboxService, MailboxRepository $mailboxRepository): int
    {
        $identifier = $this->argument('mailbox');

        $mailboxes = $mailboxRepository->toFetch($identifier);

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
