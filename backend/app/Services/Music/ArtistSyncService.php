<?php

namespace App\Services\Music;

use App\Enums\ReleaseType;
use App\Enums\SyncStatus;
use App\Models\Artist;
use App\Models\Release;
use App\Music\Contracts\MusicCatalogProvider;
use App\Music\Data\ArtistData;
use App\Music\Data\ReleaseCollectionData;
use App\Music\Data\ReleaseData;
use App\Music\Support\MusicRequestBudget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ArtistSyncService
{
    public function __construct(private MusicCatalogProvider $provider) {}

    public function sync(Artist $artist, bool $force = false, ?string $generation = null): SyncResult
    {
        $lockSeconds = max(1, (int) config('music.lock_seconds', 300));
        $lock = Cache::lock('music:artist-sync:'.$artist->id, $lockSeconds);
        if (! $lock->get()) {
            return new SyncResult(0, 0, 0, deferred: true);
        }

        try {
            $enqueueLock = Cache::lock('music:artist-enqueue:'.$artist->id, $lockSeconds);
            if (! $enqueueLock->get()) {
                return new SyncResult(0, 0, 0, deferred: true);
            }

            try {
                $artist = Artist::query()->findOrFail($artist->getKey());
                $generationKey = 'music:artist-generation:'.$artist->id;
                if ($generation !== null && Cache::get($generationKey) !== $generation) {
                    return new SyncResult(0, 0, 0, deferred: true);
                }
                if (! $force && $this->isFresh($artist)) {
                    if ($generation !== null && $artist->sync_status !== SyncStatus::Idle) {
                        $artist->forceFill([
                            'sync_status' => SyncStatus::Idle,
                            'last_sync_error' => null,
                        ])->save();
                    }

                    return new SyncResult(0, 0, $artist->releases()->count());
                }

                if ($generation === null) {
                    $generation = (string) Str::uuid();
                    Cache::put($generationKey, $generation, max(1, (int) config('music.stuck_after_seconds', 900)));
                }

                $artist->forceFill([
                    'sync_status' => SyncStatus::Running,
                    'last_sync_attempt_at' => now(),
                    'last_sync_error' => null,
                ])->save();
            } finally {
                $enqueueLock->release();
            }

            try {
                return MusicRequestBudget::within(function () use ($artist, $generation): SyncResult {
                    MusicRequestBudget::assertAvailable();
                    $page = $this->provider->getArtist($artist->youtube_music_id);
                    MusicRequestBudget::assertAvailable();
                    $collection = $this->provider->getArtistReleases($artist->youtube_music_id);
                    MusicRequestBudget::assertAvailable();
                    $this->validateResponse($artist, $page->artist, $collection);
                    $releases = $this->enrichReleases($collection);
                    MusicRequestBudget::assertAvailable();
                    $this->validateResponse($artist, $page->artist, new ReleaseCollectionData(
                        array_column($releases, 'data'),
                        complete: true,
                    ));
                    $this->assertGeneration($artist, $generation);
                    $result = $this->persist($artist, $page->artist, $releases, $generation);
                    MusicRequestBudget::markCommitted();

                    return $result;
                });

            } catch (Throwable $exception) {
                if (Cache::get('music:artist-generation:'.$artist->id) === $generation) {
                    $artist->forceFill([
                        'sync_status' => SyncStatus::Failed,
                        'last_sync_error' => $this->safeFailure($exception),
                    ])->save();
                }
                Log::warning('Music catalog synchronization failed.', [
                    'artist_id' => $artist->id,
                    'exception_class' => $exception::class,
                ]);

                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }

    /** @param list<array{data: ReleaseData, details_synced: bool}> $releases */
    private function persist(Artist $artist, ArtistData $artistData, array $releases, string $generation): SyncResult
    {
        MusicRequestBudget::limitDatabaseWait();

        return DB::transaction(function () use ($artist, $artistData, $releases, $generation): SyncResult {
            $this->assertGeneration($artist, $generation);
            MusicRequestBudget::assertAvailable();
            $currentArtist = Artist::query()->findOrFail($artist->getKey());
            $currentArtist->name = $artistData->name;
            if ($artistData->thumbnailUrl !== null) {
                $currentArtist->thumbnail_url = $artistData->thumbnailUrl;
            }
            $currentArtist->save();

            $inserted = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($releases as $releaseEntry) {
                MusicRequestBudget::assertAvailable();
                [$release, $wasInserted, $wasUpdated] = $this->upsertRelease(
                    $releaseEntry['data'],
                    $releaseEntry['details_synced'],
                );
                $releaseData = $releaseEntry['data'];
                $creditsUpdated = $this->attachCredits($release, $currentArtist, $releaseData->artists);
                $inserted += (int) $wasInserted;
                $updated += (int) (! $wasInserted && ($wasUpdated || $creditsUpdated));
                $skipped += (int) (! $wasInserted && ! $wasUpdated && ! $creditsUpdated);
            }

            $this->assertGeneration($currentArtist, $generation);
            $currentArtist->forceFill([
                'catalog_synced_at' => now(),
                'sync_status' => SyncStatus::Idle,
                'last_sync_error' => null,
            ])->save();
            MusicRequestBudget::assertAvailable();

            return new SyncResult($inserted, $updated, $skipped);
        }, attempts: 3);
    }

    /** @return list<array{data: ReleaseData, details_synced: bool}> */
    private function enrichReleases(ReleaseCollectionData $collection): array
    {
        $metadataTtl = max(1, (int) config('music.metadata_ttl', 604800));
        $results = [];

        foreach ($collection->releases as $summary) {
            MusicRequestBudget::assertAvailable();
            MusicRequestBudget::limitDatabaseWait();
            $existing = Release::query()
                ->withCount('artists')
                ->where('youtube_music_id', $summary->externalId)
                ->first();
            $detailsAreFresh = $existing?->metadata_synced_at?->gt(now()->subSeconds($metadataTtl)) ?? false;
            $detailsExpired = $existing?->metadata_synced_at !== null && ! $detailsAreFresh;
            $hasKnownType = $summary->type !== ReleaseType::Unknown || $existing?->type !== null && $existing->type !== ReleaseType::Unknown;
            $hasKnownDate = $summary->releaseYear !== null
                || $summary->releaseDate !== null
                || $existing?->release_year !== null
                || $existing?->release_date !== null;
            $hasKnownCredits = $summary->artists !== [] || ($existing?->artists_count ?? 0) > 1;

            if ($detailsAreFresh || (! $detailsExpired && $hasKnownType && $hasKnownDate && $hasKnownCredits)) {
                $results[] = ['data' => $summary, 'details_synced' => false];

                continue;
            }

            $details = $this->provider->getRelease($summary->externalId);
            MusicRequestBudget::assertAvailable();
            if ($details->externalId !== $summary->externalId) {
                throw new \UnexpectedValueException('Provider returned mismatched release details.');
            }
            $results[] = [
                'data' => $this->mergeReleaseData($summary, $details),
                'details_synced' => true,
            ];
        }

        return $results;
    }

    private function mergeReleaseData(ReleaseData $summary, ReleaseData $details): ReleaseData
    {
        return new ReleaseData(
            externalId: $summary->externalId,
            title: $summary->title !== '' ? $summary->title : $details->title,
            type: $summary->type !== ReleaseType::Unknown ? $summary->type : $details->type,
            sourceType: $summary->sourceType ?? $details->sourceType,
            releaseYear: $summary->releaseYear ?? $details->releaseYear,
            releaseDate: $summary->releaseDate ?? $details->releaseDate,
            thumbnailUrl: $summary->thumbnailUrl ?? $details->thumbnailUrl,
            sourceUrl: $summary->sourceUrl ?? $details->sourceUrl,
            artists: $this->mergeCredits($details->artists, $summary->artists),
        );
    }

    /** @param list<ArtistData> $details @param list<ArtistData> $summary @return list<ArtistData> */
    private function mergeCredits(array $details, array $summary): array
    {
        $artists = [];
        foreach ([...$details, ...$summary] as $artist) {
            $artists[$artist->externalId] ??= $artist;
        }

        return array_values($artists);
    }

    /** @return array{Release, bool, bool} */
    private function upsertRelease(ReleaseData $data, bool $detailsSynced): array
    {
        $release = Release::query()->firstOrNew(['youtube_music_id' => $data->externalId]);
        $wasInserted = ! $release->exists;
        $attributes = ['title' => $data->title];
        if ($data->type !== ReleaseType::Unknown) {
            $attributes['type'] = $data->type;
        }
        foreach ([
            'source_type' => $data->sourceType,
            'release_year' => $data->releaseYear,
            'release_date' => $data->releaseDate,
            'thumbnail_url' => $data->thumbnailUrl,
            'source_url' => $data->sourceUrl,
        ] as $field => $value) {
            if ($value !== null) {
                $attributes[$field] = $value;
            }
        }
        if ($detailsSynced) {
            $attributes['metadata_synced_at'] = now();
        }
        $release->fill($attributes);
        $wasUpdated = ! $wasInserted && $release->isDirty();
        $release->save();

        return [$release, $wasInserted, $wasUpdated];
    }

    /** @param list<ArtistData> $creditedArtists */
    private function attachCredits(Release $release, Artist $primaryArtist, array $creditedArtists): bool
    {
        $credits = [];
        foreach ($creditedArtists as $artistData) {
            $credits[$artistData->externalId] = ['name' => $artistData->name, 'thumbnail_url' => $artistData->thumbnailUrl];
        }
        $credits[$primaryArtist->youtube_music_id] ??= ['name' => $primaryArtist->name, 'thumbnail_url' => $primaryArtist->thumbnail_url];
        $existing = $release->artists()->withPivot('position')->get()->keyBy('youtube_music_id');
        foreach ($existing as $externalId => $artist) {
            if (! array_key_exists($externalId, $credits)) {
                $credits[$externalId] = ['name' => $artist->name, 'thumbnail_url' => $artist->thumbnail_url];
            }
        }

        $pivot = [];
        $providerPositions = [];
        foreach ($creditedArtists as $position => $artistData) {
            $providerPositions[$artistData->externalId] ??= $position;
        }
        $highestExistingPosition = $existing->max(fn (Artist $artist): int => (int) $artist->pivot->position) ?? -1;
        $highestProviderPosition = $providerPositions === [] ? -1 : max($providerPositions);
        $nextPosition = max($highestExistingPosition, $highestProviderPosition) + 1;
        foreach ($credits as $externalId => $attributes) {
            $artist = Artist::query()->firstOrNew(['youtube_music_id' => $externalId]);
            $artist->name = $attributes['name'];
            if ($attributes['thumbnail_url'] !== null) {
                $artist->thumbnail_url = $attributes['thumbnail_url'];
            }
            $artist->save();
            if (array_key_exists($externalId, $providerPositions)) {
                $position = $providerPositions[$externalId];
            } elseif ($creditedArtists !== [] && $externalId === $primaryArtist->youtube_music_id) {
                $position = $nextPosition++;
            } elseif (isset($existing[$externalId])) {
                $position = $existing[$externalId]->pivot->position;
            } else {
                $position = $nextPosition++;
            }
            $pivot[$artist->id] = ['position' => $position];
        }

        $changes = $release->artists()->syncWithoutDetaching($pivot);

        return $changes['attached'] !== [] || $changes['updated'] !== [];
    }

    private function validateResponse(Artist $artist, ArtistData $artistData, ReleaseCollectionData $collection): void
    {
        if ($artistData->externalId !== $artist->youtube_music_id || trim($artistData->name) === '') {
            throw new \UnexpectedValueException('Provider returned invalid artist data.');
        }
        if (! $collection->complete) {
            throw new \UnexpectedValueException('Provider returned an incomplete release collection.');
        }
        foreach ($collection->releases as $release) {
            if (trim($release->externalId) === '' || trim($release->title) === '') {
                throw new \UnexpectedValueException('Provider returned invalid release data.');
            }
            foreach ($release->artists as $creditedArtist) {
                if (trim($creditedArtist->externalId) === '' || trim($creditedArtist->name) === '') {
                    throw new \UnexpectedValueException('Provider returned invalid artist credit data.');
                }
            }
        }
    }

    private function isFresh(Artist $artist): bool
    {
        $ttl = max(1, (int) config('music.catalog_ttl', 21600));

        return $artist->catalog_synced_at !== null && $artist->catalog_synced_at->gt(now()->subSeconds($ttl));
    }

    private function safeFailure(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \UnexpectedValueException => 'Provider returned an incomplete or invalid catalog.',
            default => 'Catalog synchronization failed ('.$exception::class.').',
        };
    }

    private function assertGeneration(Artist $artist, string $generation): void
    {
        if (Cache::get('music:artist-generation:'.$artist->id) !== $generation) {
            throw new \RuntimeException('Artist synchronization was superseded by a newer generation.');
        }
    }
}
