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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            // The document files live under a project's docs
            // folder; a row is the imported content of one Markdown file. When
            // the project goes, its imported docs go with it.
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // Path of the file relative to docs (e.g. `README.md`
            // or `guides/setup.md`), unique within a project.
            $table->string('path');
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
