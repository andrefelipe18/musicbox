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
            CREATE TABLE releases (
                id CHAR(26) PRIMARY KEY NOT NULL,
                youtube_music_id VARCHAR NOT NULL UNIQUE,
                title VARCHAR NOT NULL,
                type VARCHAR NOT NULL DEFAULT 'unknown'
                    CHECK (type IN ('album', 'ep', 'single', 'unknown')),
                source_type VARCHAR NULL,
                release_year INTEGER NULL,
                release_date DATE NULL,
                thumbnail_url VARCHAR NULL,
                source_url VARCHAR NULL,
                metadata_synced_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        SQL);
        DB::statement('CREATE INDEX releases_type_index ON releases (type)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS releases');
    }
};
