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
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            // Messages are exchanged between agents working on the same project.
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('recipient_agent_id')->constrained('agents')->cascadeOnDelete();
            $table->text('body');
            // Whether the recipient agent has read the message through the messages MCP.
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_agent_id', 'project_id', 'is_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};
