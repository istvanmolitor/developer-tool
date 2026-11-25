<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links attachments to the tasks they are attached to (many-to-many): one
     * file can hang off several tasks, and a task can carry several files.
     */
    public function up(): void
    {
        Schema::create('attachment_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['attachment_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_task');
    }
};
