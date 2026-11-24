<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('mailboxes are fetched once a day at the configured time', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains((string) $event->command, 'mailboxes:fetch'));

    expect($events)->toHaveCount(1)
        ->and($events->first())
        ->expression->toBe('0 6 * * *')
        ->timezone->toBe('Europe/Budapest')
        ->withoutOverlapping->toBeTrue();
});
