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
        Schema::table('projects', function (Blueprint $table) {
            // Tracks the background creation of the project directory:
            // "ready" for directories that already exist on disk, otherwise
            // "pending" → "provisioning" → "ready"/"failed" while a job runs
            // mkdir (new folder) or `git clone` (git link).
            $table->string('provision_status')->default('ready')->after('git_url');
            $table->text('provision_error')->nullable()->after('provision_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['provision_status', 'provision_error']);
        });
    }
};
