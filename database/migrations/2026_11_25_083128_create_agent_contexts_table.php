<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move the project, agent and user of a prompt run into an agent context — a
     * `claude -p` conversation the runs of a chat share through its session id.
     */
    public function up(): void
    {
        Schema::create('agent_contexts', function (Blueprint $table) {
            $table->id();
            // All nullable + null-on-delete so a context's history survives the
            // deletion of its project, agent or user.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The `claude -p` session id, known once the context's first run finished.
            $table->string('session_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'agent_id', 'user_id']);
        });

        Schema::table('prompt_runs', function (Blueprint $table) {
            $table->foreignId('agent_context_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Every existing run was a conversation of its own: give each one its own context.
        DB::table('prompt_runs')->orderBy('id')->lazyById()->each(function (object $run): void {
            $contextId = DB::table('agent_contexts')->insertGetId([
                'project_id' => $run->project_id,
                'agent_id' => $run->agent_id,
                'user_id' => $run->user_id,
                'created_at' => $run->created_at,
            ]);

            DB::table('prompt_runs')->where('id', $run->id)->update(['agent_context_id' => $contextId]);
        });

        // MySQL backs the project_id foreign key with the composite index, so the
        // foreign keys go first, then the index, then the columns.
        Schema::table('prompt_runs', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['agent_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('prompt_runs', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'created_at']);
            $table->dropColumn(['project_id', 'agent_id', 'user_id']);
        });
    }

    /**
     * Copy the context's project, agent and user back onto its runs.
     */
    public function down(): void
    {
        Schema::table('prompt_runs', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('agent_id')->constrained()->nullOnDelete();

            $table->index(['project_id', 'created_at']);
        });

        DB::table('agent_contexts')->orderBy('id')->lazyById()->each(function (object $context): void {
            DB::table('prompt_runs')->where('agent_context_id', $context->id)->update([
                'project_id' => $context->project_id,
                'agent_id' => $context->agent_id,
                'user_id' => $context->user_id,
            ]);
        });

        Schema::table('prompt_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_context_id');
        });

        Schema::dropIfExists('agent_contexts');
    }
};
