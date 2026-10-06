<?php

namespace App\Services\Music;

use App\Enums\SyncStatus;
use App\Jobs\SyncArtistCatalogJob;
use App\Models\Artist;
use Illuminate\Bus\UniqueLock;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ArtistCatalogService
{
    /** @return array{artist: Artist, releases: EloquentCollection, stale: bool, pending: bool} */
    public function releases(Artist $artist): array
    {
        $artist->load('releases');
        $stale = $artist->catalog_synced_at !== null && ! $this->isFresh($artist);

        if (! $this->isFresh($artist)) {
            $this->queueSync($artist);
            $artist->refresh()->load('releases');
        }

        $pending = ! $this->isFresh($artist)
            || in_array($artist->sync_status, [SyncStatus::Queued, SyncStatus::Running], true);

        return [
            'artist' => $artist,
            'releases' => $artist->releases,
            'stale' => $stale,
            'pending' => $pending,
        ];
    }

    public function queueSync(Artist $artist, bool $force = false): void
    {
        $artist = Artist::query()->findOrFail($artist->getKey());
        if (! $force && $this->isFresh($artist)) {
            return;
        }

        $lockSeconds = max(1, (int) config('music.lock_seconds', 300));
        $lock = Cache::lock('music:artist-enqueue:'.$artist->id, $lockSeconds);
        if (! $lock->get()) {
            return;
        }

        try {
            $artist->refresh();
            if (! $force && $this->isFresh($artist)) {
                return;
            }

            $stuckAfter = max(1, (int) config('music.stuck_after_seconds', 900));
            $isStuck = match ($artist->sync_status) {
                SyncStatus::Queued => $artist->last_sync_attempt_at?->lt(now()->subSeconds($stuckAfter)) ?? false,
                SyncStatus::Running => $artist->last_sync_attempt_at?->lt(now()->subSeconds($stuckAfter)) ?? false,
                default => false,
            };
            if (in_array($artist->sync_status, [SyncStatus::Queued, SyncStatus::Running], true) && ! $isStuck) {
                return;
            }

            $job = new SyncArtistCatalogJob($artist->id, $force, (string) Str::uuid());
            (new UniqueLock(Cache::store()))->release(new SyncArtistCatalogJob($artist->id));
            $generationKey = 'music:artist-generation:'.$artist->id;
            Cache::put($generationKey, $job->generation, $job->uniqueFor + $stuckAfter);
            $artist->forceFill([
                'sync_status' => SyncStatus::Queued,
                'last_sync_attempt_at' => now(),
                'last_sync_error' => null,
            ])->save();

            try {
                SyncArtistCatalogJob::dispatch($artist->id, $force, $job->generation);
            } catch (Throwable $exception) {
                (new UniqueLock(Cache::store()))->release($job);
                if (Cache::get($generationKey) === $job->generation) {
                    Cache::forget($generationKey);
                    $artist->forceFill([
                        'sync_status' => SyncStatus::Failed,
                        'last_sync_error' => 'Catalog synchronization could not be queued.',
                    ])->save();
                }
                Log::warning('Music catalog job dispatch failed.', [
                    'artist_id' => $artist->id,
                    'exception_class' => $exception::class,
                ]);
                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }

    private function isFresh(Artist $artist): bool
    {
        $ttl = max(1, (int) config('music.catalog_ttl', 21600));

        return $artist->catalog_synced_at !== null && $artist->catalog_synced_at->gt(now()->subSeconds($ttl));
    }
}
