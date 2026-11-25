<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move existing mail attachments onto the central `attachments` model, then
     * drop the dedicated `incoming_email_attachments` table. The physical files
     * stay where they are (`mail-attachments/...` on the local disk); only the
     * row moves, so `path` is copied verbatim and the bytes are never touched.
     */
    public function up(): void
    {
        if (! Schema::hasTable('incoming_email_attachments')) {
            return;
        }

        $now = now();

        $rows = DB::table('incoming_email_attachments as a')
            ->leftJoin('incoming_emails as e', 'e.id', '=', 'a.incoming_email_id')
            ->leftJoin('mailboxes as m', 'm.id', '=', 'e.mailbox_id')
            ->select([
                'a.id as old_id',
                'a.incoming_email_id',
                'a.filename',
                'a.mime_type',
                'a.size',
                'a.path',
                'a.created_at',
                'a.updated_at',
                'm.project_id',
            ])
            ->orderBy('a.id')
            ->get();

        foreach ($rows as $row) {
            $attachmentId = DB::table('attachments')->insertGetId([
                'project_id' => $row->project_id,
                'disk' => 'local',
                'path' => $row->path,
                'filename' => $row->filename,
                'mime_type' => $row->mime_type ?? 'application/octet-stream',
                'size' => $row->size ?? 0,
                'checksum' => null,
                'source' => 'mail',
                'relative_path' => null,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);

            DB::table('attachment_incoming_email')->insert([
                'attachment_id' => $attachmentId,
                'incoming_email_id' => $row->incoming_email_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::dropIfExists('incoming_email_attachments');
    }

    /**
     * Recreate the table and copy the mail attachments back from `attachments`.
     * Best effort: the bytes never moved, so the restored rows point at the same
     * files.
     */
    public function down(): void
    {
        if (! Schema::hasTable('incoming_email_attachments')) {
            Schema::create('incoming_email_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('incoming_email_id')->constrained()->cascadeOnDelete();
                $table->string('filename');
                $table->string('mime_type')->default('application/octet-stream');
                $table->unsignedBigInteger('size')->default(0);
                $table->string('path');
                $table->timestamps();
            });
        }

        $mailAttachments = DB::table('attachments as a')
            ->join('attachment_incoming_email as p', 'p.attachment_id', '=', 'a.id')
            ->where('a.source', 'mail')
            ->select(['a.id', 'p.incoming_email_id', 'a.filename', 'a.mime_type', 'a.size', 'a.path', 'a.created_at', 'a.updated_at'])
            ->orderBy('a.id')
            ->get();

        foreach ($mailAttachments as $row) {
            DB::table('incoming_email_attachments')->insert([
                'incoming_email_id' => $row->incoming_email_id,
                'filename' => $row->filename,
                'mime_type' => $row->mime_type,
                'size' => $row->size,
                'path' => $row->path,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        DB::table('attachments')->where('source', 'mail')->delete();
    }
};
