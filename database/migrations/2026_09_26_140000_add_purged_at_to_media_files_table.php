<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P7-011 tombstone marker (D7-06). Nullable purge timestamp on the
     * owning media row: auto-purge deletes BYTES and tombstones the row,
     * never hard-deletes domain/history records (see §8.4 of the P7-011
     * contract). Additive, backward-compatible (null for all existing
     * rows); rolled back by dropping the column.
     */
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->timestamp('purged_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->dropColumn('purged_at');
        });
    }
};
