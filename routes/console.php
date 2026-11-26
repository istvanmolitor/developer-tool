<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Download the new messages of every active mailbox once a day.
Schedule::command('mailboxes:fetch')
    ->dailyAt(config('developer-tool.mailbox_fetch_time'))
    ->timezone(config('developer-tool.mailbox_fetch_timezone'))
    ->withoutOverlapping();
