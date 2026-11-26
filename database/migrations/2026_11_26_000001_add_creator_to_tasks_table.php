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
        Schema::table('tasks', function (Blueprint $table) {
            // Who created the task: either a user (web UI) or an agent (tasks MCP).
            // Exactly one is set; both null for rows created before this column
            // existed. Deleting the creator leaves the task but clears the link.
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('agent_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by_agent_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('agents')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('created_by_agent_id');
        });
    }
};
