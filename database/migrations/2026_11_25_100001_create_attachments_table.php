<?php

use App\Services\AttachmentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The central store for attached files. Every row is one physical file on a
     * filesystem disk (its bytes live under `path` on `disk`), scoped to a
     * project. Files reach the table through {@see AttachmentService}:
     * uploaded (source=upload), synced from the project's attachments folder
     * (source=sync), or saved from a downloaded message (source=mail). Tasks and
     * messages reference attachments through their own pivot tables, so one file
     * can be shared by several of them.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            // Sync and task uploads always set the project; a mail's project comes
            // from its mailbox, which may have none, so the column is nullable.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            // Original / display name; the bytes themselves live under `path`.
            $table->string('filename');
            $table->string('mime_type')->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);
            // sha256 of the contents, used by the folder sync to detect changes.
            $table->string('checksum')->nullable();
            // Where the file came from: upload | sync | mail.
            $table->string('source')->default('upload');
            // For source=sync: the path relative to the project's attachments
            // folder. The sync upserts on (project_id, relative_path).
            $table->string('relative_path')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'relative_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
