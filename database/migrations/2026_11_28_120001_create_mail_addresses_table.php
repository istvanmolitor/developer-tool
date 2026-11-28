<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every address seen in incoming and outgoing mail, kept unique so each one
     * can be named and flagged as spam, and so its whole conversation is reachable.
     */
    public function up(): void
    {
        Schema::create('mail_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('mail')->unique();
            $table->string('name')->nullable();
            $table->boolean('is_spam')->default(false);
            $table->timestamps();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_addresses');
    }

    /**
     * Record every address already stored in incoming and outgoing mail, so the
     * table reflects the existing history, not only mail that arrives from now on.
     */
    private function backfill(): void
    {
        // Address (lowercased) => best known name (from an incoming sender), or null.
        $addresses = [];

        $remember = function (?string $mail, ?string $name = null) use (&$addresses): void {
            $mail = mb_strtolower(trim((string) $mail));

            if ($mail === '') {
                return;
            }

            if (! array_key_exists($mail, $addresses)) {
                $addresses[$mail] = null;
            }

            if ($addresses[$mail] === null && is_string($name) && trim($name) !== '') {
                $addresses[$mail] = trim($name);
            }
        };

        DB::table('incoming_emails')
            ->select(['from_address', 'from_name', 'to', 'cc'])
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($remember): void {
                foreach ($rows as $row) {
                    $remember($row->from_address, $row->from_name);

                    foreach ((array) json_decode((string) ($row->to ?? '[]'), true) as $address) {
                        $remember(is_string($address) ? $address : null);
                    }

                    foreach ((array) json_decode((string) ($row->cc ?? '[]'), true) as $address) {
                        $remember(is_string($address) ? $address : null);
                    }
                }
            });

        DB::table('outgoing_emails')
            ->select(['to'])
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($remember): void {
                foreach ($rows as $row) {
                    foreach ((array) json_decode((string) ($row->to ?? '[]'), true) as $address) {
                        $remember(is_string($address) ? $address : null);
                    }
                }
            });

        if ($addresses === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($addresses as $mail => $name) {
            $rows[] = [
                'mail' => $mail,
                'name' => $name,
                'is_spam' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('mail_addresses')->insertOrIgnore($chunk);
        }
    }
};
