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
        Schema::table('prompt_runs', function (Blueprint $table) {
            // USD cost reported by `claude -p --output-format json`
            // (total_cost_usd). Nullable: older runs and non-JSON results
            // carry no cost. decimal(12, 6) keeps sub-cent precision.
            $table->decimal('cost_usd', 12, 6)->nullable()->after('error_output');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prompt_runs', function (Blueprint $table) {
            $table->dropColumn('cost_usd');
        });
    }
};
