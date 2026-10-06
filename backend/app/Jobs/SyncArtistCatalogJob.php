<?php

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Models\Artist;
use App\Services\Music\ArtistSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncArtistCatalogJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 70;

    public int $uniqueFor;

    public function __construct(public string $artistId, public bool $force = false, public ?string $generation = null)
    {
        $lockSeconds = max(1, (int) config('music.lock_seconds', 300));
        $queueConnection = (string) config('queue.default');
        $retryAfter = max(0, (int) config("queue.connections.{$queueConnection}.retry_after", 0));

        if ($retryAfter > 0 && $retryAfter <= $this->timeout) {
            throw new \LogicException('Queue retry_after must exceed the artist sync job timeout.');
        }

        $retryHorizon = ($this->timeout * $this->tries)
            + ($retryAfter * max(0, $this->tries - 1))
            + array_sum($this->backoff())
            + $lockSeconds + 1;
        $this->uniqueFor = max($lockSeconds, $retryHorizon + 1);
    }

    public function uniqueId(): string
    {
        return $this->artistId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(ArtistSyncService $service): void
    {
        $artist = Artist::query()->findOrFail($this->artistId);
        if ($this->generation !== null && Cache::get('music:artist-generation:'.$this->artistId) !== $this->generation) {
            return;
        }

        $result = $service->sync($artist, $this->force, $this->generation);
        if ($result->deferred) {
            $this->release(max(1, (int) config('music.lock_seconds', 300)) + 1);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $lock = Cache::lock('music:artist-enqueue:'.$this->artistId, max(1, (int) config('music.lock_seconds', 300)));
        if ($lock->get()) {
            try {
                $artist = Artist::query()->find($this->artistId);
                $generationMatches = $this->generation === null
                    || Cache::get('music:artist-generation:'.$this->artistId) === $this->generation;
                if ($generationMatches && $artist !== null && in_array($artist->sync_status, [SyncStatus::Queued, SyncStatus::Running], true)) {
                    $artist->forceFill([
                        'sync_status' => SyncStatus::Failed,
                        'last_sync_error' => 'Catalog synchronization exhausted its retries.',
                    ])->save();
                }
            } finally {
                $lock->release();
            }
        }

        Log::error('Music catalog job exhausted retries.', [
            'artist_id' => $this->artistId,
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
