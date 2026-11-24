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
        Schema::table('incoming_emails', function (Blueprint $table) {
            // When the message was first opened in the app; null means unread.
            $table->timestamp('read_at')->nullable()->after('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incoming_emails', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
