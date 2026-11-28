<?php

use App\Models\IncomingEmail;
use App\Models\MailAddress;
use App\Models\OutgoingEmail;

test('a received message records its sender, recipients and copies', function () {
    IncomingEmail::factory()->create([
        'from_address' => 'From@Example.test',
        'from_name' => 'Feladó',
        'to' => ['To@Example.test'],
        'cc' => ['Cc@Example.test'],
    ]);

    expect(MailAddress::where('mail', 'from@example.test')->value('name'))->toBe('Feladó');
    $this->assertDatabaseHas('mail_addresses', ['mail' => 'to@example.test']);
    $this->assertDatabaseHas('mail_addresses', ['mail' => 'cc@example.test']);
});

test('an outgoing message records its recipients', function () {
    OutgoingEmail::factory()->create(['to' => ['Recipient@Example.test']]);

    $this->assertDatabaseHas('mail_addresses', ['mail' => 'recipient@example.test']);
});

test('a known address is not duplicated and keeps its name', function () {
    MailAddress::factory()->create(['mail' => 'known@example.test', 'name' => 'Ismert']);

    IncomingEmail::factory()->create([
        'from_address' => 'known@example.test',
        'from_name' => 'Másik név',
        'to' => [],
        'cc' => null,
    ]);

    expect(MailAddress::where('mail', 'known@example.test')->count())->toBe(1);
    expect(MailAddress::where('mail', 'known@example.test')->value('name'))->toBe('Ismert');
});

test('a missing name is filled in from the sender', function () {
    MailAddress::factory()->create(['mail' => 'noname@example.test', 'name' => null]);

    IncomingEmail::factory()->create([
        'from_address' => 'noname@example.test',
        'from_name' => 'Kapott név',
        'to' => [],
        'cc' => null,
    ]);

    expect(MailAddress::where('mail', 'noname@example.test')->value('name'))->toBe('Kapott név');
});
