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
        Schema::create('artist_release', function (Blueprint $table) {
            $table->ulid('artist_id');
            $table->ulid('release_id');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->foreign('artist_id')->references('id')->on('artists')->cascadeOnDelete();
            $table->foreign('release_id')->references('id')->on('releases')->cascadeOnDelete();
            $table->unique(['artist_id', 'release_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artist_release');
    }
};
