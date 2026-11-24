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
        Schema::table('mailboxes', function (Blueprint $table) {
            // Highest IMAP UID ever downloaded, so deleted messages are not fetched again.
            $table->unsignedBigInteger('last_uid')->nullable()->after('last_fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn('last_uid');
        });
    }
};
