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
            CREATE TABLE user_releases (
                id CHAR(26) PRIMARY KEY NOT NULL,
                user_id CHAR(26) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                release_id CHAR(26) NOT NULL REFERENCES releases(id) ON DELETE CASCADE,
                status VARCHAR NOT NULL CHECK (status IN ('want_to_listen', 'listening', 'listened')),
                rating INTEGER NULL CHECK (rating IS NULL OR (typeof(rating) = 'integer' AND rating BETWEEN 1 AND 5)),
                listened_at DATE NULL,
                notes TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CONSTRAINT user_releases_user_release_unique UNIQUE (user_id, release_id),
                CONSTRAINT user_releases_listened_fields_check CHECK (
                    status = 'listened' OR (rating IS NULL AND listened_at IS NULL)
                )
            )
        SQL);
        DB::statement('CREATE INDEX user_releases_user_status_rating_index ON user_releases (user_id, status, rating)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS user_releases');
    }
};
