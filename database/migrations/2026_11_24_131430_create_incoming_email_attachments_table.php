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
        Schema::create('incoming_email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_email_id')->constrained()->cascadeOnDelete();
            // Original file name from the message; the file itself lives on the private disk under `path`.
            $table->string('filename');
            $table->string('mime_type')->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_email_attachments');
    }
};
