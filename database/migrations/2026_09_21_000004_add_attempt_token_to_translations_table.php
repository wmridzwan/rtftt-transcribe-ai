<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P5-004 cycle 2: explicit translation attempt identity.
     *
     * Each queued execution attempt receives a fresh token. Every writer,
     * failure handler, retry, and stale-recovery mutation must prove it is
     * acting on the current attempt (token match) before changing state.
     */
    public function up(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->string('attempt_token', 64)->nullable()->after('status');
            $table->index('attempt_token');
        });
    }

    public function down(): void
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->dropIndex(['attempt_token']);
            $table->dropColumn('attempt_token');
        });
    }
};
