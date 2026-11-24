<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `slug` becomes the machine identifier (the definition file's name and the
     * Claude Code agent name), freeing `name` to hold a human-readable label.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        DB::table('agents')->orderBy('id')->each(function (object $agent): void {
            DB::table('agents')
                ->where('id', $agent->id)
                ->update(['slug' => Str::slug($agent->name) ?: 'agent-'.$agent->id]);
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
