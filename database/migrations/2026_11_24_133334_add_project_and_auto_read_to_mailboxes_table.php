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
        Schema::table('mailboxes', function (Blueprint $table) {
            // The project the agent works on when it reads a message from this mailbox.
            $table->foreignId('project_id')
                ->nullable()
                ->after('agent_id')
                ->constrained()
                ->nullOnDelete();
            // When on, every newly downloaded message is handed to the agent right away.
            $table->boolean('auto_read')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('auto_read');
        });
    }
};
