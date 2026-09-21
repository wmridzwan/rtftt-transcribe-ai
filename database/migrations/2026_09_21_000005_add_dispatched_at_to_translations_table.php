<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P5-004B: record that the queue accepted an attempt's message.
     *
     * A `queued` row whose dispatch failed keeps `dispatched_at` null, so a
     * later request or retry can safely re-dispatch the same attempt token
     * without creating a second row.
     */
    public function up(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->timestamp('dispatched_at')->nullable()->after('attempt_token');
        });
    }

    public function down(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->dropColumn('dispatched_at');
        });
    }
};
