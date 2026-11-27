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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            // Machine-readable identifier, e.g. `documentarian`. Matches the
            // `name` front matter of the `.claude/agents/*.md` definitions.
            $table->string('name')->unique();
            $table->text('description')->nullable();
            // Default model id, e.g. `claude-opus-4-8` (nullable = inherit).
            $table->string('model')->nullable();
            // Allowed tool names as a JSON list, e.g. ["Read", "Grep", "Edit"].
            $table->json('tools')->nullable();
            // The agent's instructions — the Markdown body of its definition.
            $table->longText('system_prompt');
            // Profile picture: the file's path on the private `local` disk
            // (see AgentAvatarService). Null = no picture, the UI falls back to
            // the agent's initials.
            $table->string('avatar_path')->nullable();
            // Where a seeded agent was loaded from, for traceability. Null when
            // the agent was created through the UI rather than imported.
            $table->string('source_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
