<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE artists (
                id CHAR(26) PRIMARY KEY NOT NULL,
                youtube_music_id VARCHAR NOT NULL UNIQUE,
                name VARCHAR NOT NULL,
                thumbnail_url VARCHAR NULL,
                sync_status VARCHAR NOT NULL DEFAULT 'idle'
                    CHECK (sync_status IN ('idle', 'queued', 'running', 'failed')),
                catalog_synced_at DATETIME NULL,
                last_sync_attempt_at DATETIME NULL,
                last_sync_error TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS artists');
    }
};
