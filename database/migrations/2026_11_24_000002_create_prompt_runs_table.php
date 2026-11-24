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
        Schema::create('prompt_runs', function (Blueprint $table) {
            $table->id();
            // The project the prompt runs against, the agent it runs as (if
            // any), and the user who started it. All nullable + null-on-delete
            // so a run's history survives the deletion of any of them.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('prompt');
            $table->string('status')->default('pending')->index();
            $table->integer('exit_code')->nullable();
            $table->longText('output')->nullable();
            $table->longText('error_output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // A run is created once and streamed onto in place — there is no
            // meaningful "updated_at", so only track when it was created.
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_runs');
    }
};
