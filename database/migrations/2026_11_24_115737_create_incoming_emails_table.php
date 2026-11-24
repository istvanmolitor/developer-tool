<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incoming_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            // IMAP UID within the mailbox folder — the de-duplication key.
            $table->unsignedBigInteger('uid');
            $table->string('message_id')->nullable()->index();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            // Recipient address lists as JSON, e.g. ["a@example.com"].
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->string('subject', 1000)->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['mailbox_id', 'uid']);
            $table->index(['mailbox_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_emails');
    }
};
