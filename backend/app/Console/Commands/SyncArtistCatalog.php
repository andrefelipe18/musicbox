<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Services\Music\ArtistSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncArtistCatalog extends Command
{
    protected $signature = 'music:sync-artist {artist} {--force}';

    protected $description = 'Synchronize an artist release catalog.';

    public function handle(ArtistSyncService $service): int
    {
        $artist = Artist::query()->find($this->argument('artist'));
        if ($artist === null) {
            $this->error('Artist not found. Provide its internal ULID.');

            return self::FAILURE;
        }

        try {
            $result = $service->sync($artist, (bool) $this->option('force'));
            if ($result->deferred) {
                $this->error('Artist catalog synchronization is already running.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('Artist catalog synchronization failed.');

            return self::FAILURE;
        }

        $this->info("Inserted: {$result->inserted}; updated: {$result->updated}; skipped: {$result->skipped}.");

        return self::SUCCESS;
    }
}
