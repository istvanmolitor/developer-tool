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
        Schema::table('incoming_emails', function (Blueprint $table) {
            // The prompt run in which the mailbox's agent read this message.
            $table->foreignId('prompt_run_id')
                ->nullable()
                ->after('read_at')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incoming_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prompt_run_id');
        });
    }
};
