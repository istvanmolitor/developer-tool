<?php

use App\Models\IncomingEmail;
use App\Models\MailAddress;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('the index lists the addresses', function () {
    MailAddress::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->get(route('mail-addresses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MailAddresses/Index')
            ->has('mailAddresses.data', 3)
        );
});

test('the index can be searched by address or name', function () {
    MailAddress::factory()->create(['mail' => 'alice@example.test', 'name' => 'Alice']);
    MailAddress::factory()->create(['mail' => 'bob@example.test', 'name' => 'Bob']);

    $this->actingAs($this->user)
        ->get(route('mail-addresses.index', ['q' => 'alice']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('mailAddresses.data', 1)
            ->where('mailAddresses.data.0.mail', 'alice@example.test')
        );
});

test('the index can be sorted by a whitelisted column', function () {
    MailAddress::factory()->create(['mail' => 'aaa@example.test']);
    MailAddress::factory()->create(['mail' => 'zzz@example.test']);

    $this->actingAs($this->user)
        ->get(route('mail-addresses.index', ['sort' => 'mail', 'direction' => 'desc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mailAddresses.data.0.mail', 'zzz@example.test')
            ->where('filters.sort', 'mail')
            ->where('filters.direction', 'desc')
        );
});

test('an address is created and stored lowercased', function () {
    $this->actingAs($this->user)
        ->post(route('mail-addresses.store'), [
            'mail' => 'New.Contact@Example.TEST',
            'name' => 'Új kapcsolat',
            'is_spam' => true,
        ])
        ->assertRedirect(route('mail-addresses.index'));

    $address = MailAddress::sole();

    expect($address)
        ->mail->toBe('new.contact@example.test')
        ->name->toBe('Új kapcsolat')
        ->is_spam->toBeTrue();
});

test('a duplicate address is rejected case-insensitively', function () {
    MailAddress::factory()->create(['mail' => 'dup@example.test']);

    $this->actingAs($this->user)
        ->post(route('mail-addresses.store'), ['mail' => 'DUP@example.test'])
        ->assertSessionHasErrors('mail');

    expect(MailAddress::count())->toBe(1);
});

test('an invalid address is rejected', function () {
    $this->actingAs($this->user)
        ->post(route('mail-addresses.store'), ['mail' => 'not-an-email'])
        ->assertSessionHasErrors('mail');

    expect(MailAddress::count())->toBe(0);
});

test('an address can be updated', function () {
    $address = MailAddress::factory()->create(['name' => null, 'is_spam' => false]);

    $this->actingAs($this->user)
        ->put(route('mail-addresses.update', $address), [
            'mail' => $address->mail,
            'name' => 'Megnevezve',
            'is_spam' => true,
        ])
        ->assertRedirect(route('mail-addresses.show', $address));

    expect($address->fresh())
        ->name->toBe('Megnevezve')
        ->is_spam->toBeTrue();
});

test('an address can be deleted', function () {
    $address = MailAddress::factory()->create();

    $this->actingAs($this->user)
        ->delete(route('mail-addresses.destroy', $address))
        ->assertRedirect(route('mail-addresses.index'));

    $this->assertDatabaseMissing('mail_addresses', ['id' => $address->id]);
});

test('the show page gathers the conversation from and to the address', function () {
    $address = MailAddress::factory()->create(['mail' => 'client@example.test']);
    $mailbox = Mailbox::factory()->create();

    IncomingEmail::factory()->for($mailbox)->create([
        'from_address' => 'client@example.test',
        'to' => ['support@example.test'],
        'cc' => null,
        'subject' => 'Kérdés',
    ]);
    OutgoingEmail::factory()->create([
        'mailbox_id' => $mailbox->id,
        'to' => ['client@example.test'],
        'subject' => 'Válasz',
    ]);

    $this->actingAs($this->user)
        ->get(route('mail-addresses.show', $address))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MailAddresses/Show')
            ->where('mailAddress.mail', 'client@example.test')
            ->has('conversation', 2)
        );
});

test('guests cannot reach the addresses', function () {
    $this->get(route('mail-addresses.index'))->assertRedirect(route('login'));
});
