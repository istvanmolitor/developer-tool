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
        Schema::create('outgoing_emails', function (Blueprint $table) {
            $table->id();
            // The mailbox whose SMTP account sends the message.
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            // The message this one answers; the reply is kept even if that message is deleted.
            $table->foreignId('incoming_email_id')->nullable()->constrained()->nullOnDelete();
            // The agent run that wrote the message.
            $table->foreignId('prompt_run_id')->nullable()->constrained()->nullOnDelete();
            $table->json('to');
            $table->string('subject');
            $table->longText('body_text');
            // Message-ID of the answered message, for the In-Reply-To / References headers.
            $table->string('in_reply_to')->nullable();
            // draft: waits for approval, sent: delivered, failed: the SMTP send failed.
            $table->string('status')->default('draft')->index();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outgoing_emails');
    }
};
