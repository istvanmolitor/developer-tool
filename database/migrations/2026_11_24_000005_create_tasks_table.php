<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            // Every task belongs to a project; deleting the project removes its tasks.
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // Workflow state: todo / in_progress / done.
            $table->string('status')->default(TaskStatus::Todo->value);
            // Importance: low / medium / high.
            $table->string('priority')->default(TaskPriority::Medium->value);
            $table->date('due_date')->nullable();
            // Manual ordering within a project's list (ascending).
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
