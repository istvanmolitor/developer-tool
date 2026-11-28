<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links attachments to the outgoing messages they are sent with.
     */
    public function up(): void
    {
        Schema::create('attachment_outgoing_email', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outgoing_email_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['attachment_id', 'outgoing_email_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_outgoing_email');
    }
};
