<?php

use App\Enums\ReleaseType;
use App\Enums\SyncStatus;
use App\Enums\UserReleaseStatus;
use App\Jobs\SyncArtistCatalogJob;
use App\Models\Artist;
use App\Models\Release;
use App\Models\UserRelease;
use App\Music\Contracts\MusicCatalogProvider;
use App\Music\Data\ArtistData;
use App\Music\Data\ArtistPageData;
use App\Music\Data\ReleaseCollectionData;
use App\Music\Data\ReleaseData;
use App\Services\Music\ArtistCatalogService;
use App\Services\Music\ArtistSearchService;
use App\Services\Music\ArtistSyncService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

final class ArtistSyncFakeProvider implements MusicCatalogProvider
{
    /** @var list<ArtistData> */
    public array $searchResults = [];

    public ?ReleaseCollectionData $releaseResults = null;

    /** @var array<string, ReleaseData> */
    public array $releaseDetails = [];

    public ?Throwable $failure = null;

    public ?Closure $beforeDetail = null;

    public int $searchCalls = 0;

    public int $pageCalls = 0;

    public int $detailCalls = 0;

    public function searchArtists(string $query): array
    {
        $this->searchCalls++;

        return $this->searchResults;
    }

    public function getArtist(string $externalId): ArtistPageData
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new ArtistPageData(new ArtistData($externalId, 'Artist'), []);
    }

    public function getArtistReleases(string $externalId): ReleaseCollectionData
    {
        $this->pageCalls++;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->releaseResults ?? new ReleaseCollectionData([]);
    }

    public function getRelease(string $externalId): ReleaseData
    {
        $this->detailCalls++;
        ($this->beforeDetail)?->__invoke();

        return $this->releaseDetails[$externalId] ?? throw new LogicException('Unexpected release detail request.');
    }
}

function artistSyncProvider(): ArtistSyncFakeProvider
{
    $provider = new ArtistSyncFakeProvider;
    app()->instance(MusicCatalogProvider::class, $provider);
    Http::preventStrayRequests();

    return $provider;
}

it('caches search by normalized query and persists candidates without marking catalogs fresh', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $provider->searchResults = [new ArtistData('yt-artist-1', 'Artist One', 'https://example.test/a.jpg')];

    $first = app(ArtistSearchService::class)->search('  Artist   One ');
    $second = app(ArtistSearchService::class)->search('artist one');

    expect($provider->searchCalls)->toBe(1)
        ->and($first->pluck('id')->all())->toBe($second->pluck('id')->all());
    $this->assertDatabaseHas('artists', [
        'youtube_music_id' => 'yt-artist-1',
        'name' => 'Artist One',
        'catalog_synced_at' => null,
    ]);
});

it('returns cached search candidates separately by configured language and region', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $provider->searchResults = [new ArtistData('yt-artist-1', 'Artist One')];
    $service = app(ArtistSearchService::class);

    $service->search('Artist');
    config(['music.youtube_music.hl' => 'pt', 'music.youtube_music.gl' => 'BR']);
    $service->search('Artist');

    expect($provider->searchCalls)->toBe(2);
});

it('refreshes search results after the configured search TTL expires', function () {
    Cache::setDefaultDriver('array');
    config(['music.search_ttl' => 60]);
    $provider = artistSyncProvider();
    $provider->searchResults = [new ArtistData('yt-first', 'First')];
    $service = app(ArtistSearchService::class);

    $service->search('Artist');
    $provider->searchResults = [new ArtistData('yt-second', 'Second')];
    $this->travel(61)->seconds();
    $results = $service->search('Artist');

    expect($provider->searchCalls)->toBe(2)
        ->and($results->first()->youtube_music_id)->toBe('yt-second');
});

it('rejects malformed artist search DTOs before persisting candidates', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $provider->searchResults = [new ArtistData('', 'Invalid')];

    expect(fn () => app(ArtistSearchService::class)->search('Invalid'))
        ->toThrow(UnexpectedValueException::class);

    $this->assertDatabaseMissing('artists', ['youtube_music_id' => '']);
});

it('queues the first catalog read and exposes pending state', function () {
    Cache::setDefaultDriver('array');
    Queue::fake();
    artistSyncProvider();
    $artist = Artist::factory()->create();

    $result = app(ArtistCatalogService::class)->releases($artist);

    expect($result['artist']->is($artist))->toBeTrue()
        ->and($result['stale'])->toBeFalse()
        ->and($result['pending'])->toBeTrue()
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Queued);
    Queue::assertPushed(SyncArtistCatalogJob::class, fn ($job) => $job->artistId === $artist->id);
});

it('serves fresh catalogs without contacting provider or queue', function () {
    Cache::setDefaultDriver('array');
    Queue::fake();
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create(['catalog_synced_at' => now()]);

    $result = app(ArtistCatalogService::class)->releases($artist);

    expect($result['stale'])->toBeFalse()
        ->and($result['pending'])->toBeFalse()
        ->and($provider->pageCalls)->toBe(0);
    Queue::assertNothingPushed();
});

it('serves expired catalogs as stale while queueing a refresh', function () {
    Cache::setDefaultDriver('array');
    Queue::fake();
    artistSyncProvider();
    $artist = Artist::factory()->create(['catalog_synced_at' => now()->subSeconds(21601)]);

    $result = app(ArtistCatalogService::class)->releases($artist);

    expect($result['stale'])->toBeTrue()
        ->and($result['pending'])->toBeTrue();
    Queue::assertPushed(SyncArtistCatalogJob::class);
});

it('deduplicates refresh jobs and recovers stale running state', function () {
    Cache::setDefaultDriver('array');
    Queue::fake();
    artistSyncProvider();
    $artist = Artist::factory()->create([
        'sync_status' => SyncStatus::Running,
        'last_sync_attempt_at' => now()->subSeconds(901),
    ]);

    app(ArtistCatalogService::class)->releases($artist);
    app(ArtistCatalogService::class)->releases($artist->fresh());

    expect($artist->fresh()->sync_status)->toBe(SyncStatus::Queued);
    Queue::assertPushed(SyncArtistCatalogJob::class, 1);
});

it('does not fetch again when catalog becomes fresh before job acquires the lock', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create([
        'catalog_synced_at' => now(),
        'sync_status' => SyncStatus::Queued,
    ]);
    Cache::put('music:artist-generation:'.$artist->id, 'queued-generation', 1200);
    $result = (new SyncArtistCatalogJob($artist->id, generation: 'queued-generation'))->handle(app(ArtistSyncService::class));

    expect($provider->pageCalls)->toBe(0)
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Idle)
        ->and($result)->toBeNull();
});

it('skips synchronization when another execution owns the shared artist lock', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create();
    $lock = Cache::lock('music:artist-sync:'.$artist->id, 300);
    expect($lock->get())->toBeTrue();

    $result = app(ArtistSyncService::class)->sync($artist, true);
    $lock->release();

    expect([$result->inserted, $result->updated, $result->skipped, $result->deferred])->toBe([0, 0, 0, true])
        ->and($provider->pageCalls)->toBe(0);
});

it('releases a unique job until a dead worker lock expires, then syncs on retry', function () {
    Cache::setDefaultDriver('database');
    Queue::fake();
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create();
    $deadWorkerLock = Cache::lock('music:artist-sync:'.$artist->id, 300);
    expect($deadWorkerLock->get())->toBeTrue();

    app(ArtistCatalogService::class)->queueSync($artist, force: true);
    $job = Queue::pushed(SyncArtistCatalogJob::class)->first();
    expect($job)->toBeInstanceOf(SyncArtistCatalogJob::class)
        ->and($job->generation)->not->toBeNull();
    $job->withFakeQueueInteractions();
    $job->handle(app(ArtistSyncService::class));
    $job->assertReleased(301);
    expect($provider->pageCalls)->toBe(0);

    $this->travel(302)->seconds();
    $job->handle(app(ArtistSyncService::class));
    (new UniqueLock(Cache::store()))->release($job);
    expect($provider->pageCalls)->toBe(1)
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Idle);
});

it('serializes command state publication with exhausted job callbacks', function () {
    Cache::setDefaultDriver('database');
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create(['sync_status' => SyncStatus::Running]);
    $generation = 'current-generation';
    Cache::put('music:artist-generation:'.$artist->id, $generation, 1200);
    $commandExitCode = null;
    $interrupted = false;

    Artist::saving(function (Artist $savingArtist) use ($artist, &$commandExitCode, &$interrupted): void {
        if ($interrupted
            || $savingArtist->sync_status !== SyncStatus::Failed
            || $savingArtist->last_sync_error !== 'Catalog synchronization exhausted its retries.') {
            return;
        }

        $interrupted = true;
        $commandExitCode = Artisan::call('music:sync-artist', ['artist' => $artist->id, '--force' => true]);
    });

    (new SyncArtistCatalogJob($artist->id, generation: $generation))->failed(new RuntimeException('retry exhausted'));

    expect($interrupted)->toBeTrue()
        ->and($commandExitCode)->toBe(1)
        ->and(Cache::get('music:artist-generation:'.$artist->id))->toBe($generation)
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Failed)
        ->and($provider->pageCalls)->toBe(0);
});

it('does not invalidate a forced queued generation when command sees fresh catalog', function () {
    Cache::setDefaultDriver('array');
    Queue::fake();
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create([
        'catalog_synced_at' => now(),
        'sync_status' => SyncStatus::Idle,
    ]);
    app(ArtistCatalogService::class)->queueSync($artist, force: true);
    $job = Queue::pushed(SyncArtistCatalogJob::class)->first();
    $generation = $job->generation;

    $this->artisan('music:sync-artist', ['artist' => $artist->id])
        ->expectsOutput('Inserted: 0; updated: 0; skipped: 0.')
        ->assertSuccessful();

    expect(Cache::get('music:artist-generation:'.$artist->id))->toBe($generation)
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Queued);
    $job->handle(app(ArtistSyncService::class));
    (new UniqueLock(Cache::store()))->release($job);

    expect($provider->pageCalls)->toBe(1)
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Idle);
});

it('keeps job uniqueness through timeout, retries, backoff, lock wait, and queue reservation', function () {
    config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 90]);
    $job = new SyncArtistCatalogJob('artist-ulid');
    $retryHorizon = ($job->timeout * $job->tries) + (90 * ($job->tries - 1)) + array_sum($job->backoff()) + config('music.lock_seconds') + 1;

    expect($job->uniqueFor)->toBeGreaterThan($retryHorizon)
        ->and(90)->toBeGreaterThan($job->timeout);
});

it('rejects queue retry-after values that cannot outlast the job timeout', function () {
    config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 69]);

    expect(fn () => new SyncArtistCatalogJob('artist-ulid'))
        ->toThrow(LogicException::class);
});

it('compensates queued state when job dispatch fails', function () {
    Cache::setDefaultDriver('array');
    artistSyncProvider();
    $artist = Artist::factory()->create();
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('private queue details'));

    expect(fn () => app(ArtistCatalogService::class)->queueSync($artist))
        ->toThrow(RuntimeException::class, 'private queue details');

    expect($artist->fresh()->sync_status)->toBe(SyncStatus::Failed)
        ->and($artist->fresh()->last_sync_error)->toBe('Catalog synchronization could not be queued.');
});

it('releases the unique job lock when dispatch fails', function () {
    Cache::setDefaultDriver('array');
    artistSyncProvider();
    $artist = Artist::factory()->create();
    $locks = new UniqueLock(Cache::store());
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue unavailable'));

    try {
        app(ArtistCatalogService::class)->queueSync($artist);
    } catch (RuntimeException) {
    }

    expect($locks->acquire(new SyncArtistCatalogJob($artist->id)))->toBeTrue();
});

it('rejects invalid retry-after before changing queued status', function () {
    Cache::setDefaultDriver('array');
    Queue::fake();
    config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 70]);
    artistSyncProvider();
    $artist = Artist::factory()->create();

    expect(fn () => app(ArtistCatalogService::class)->queueSync($artist))
        ->toThrow(LogicException::class);

    expect($artist->fresh()->sync_status)->toBe(SyncStatus::Idle);
    Queue::assertNothingPushed();
});

it('recovers queued state by enqueue timestamp even after unrelated artist updates', function () {
    Cache::setDefaultDriver('array');
    Queue::fake();
    artistSyncProvider();
    $artist = Artist::factory()->create([
        'sync_status' => SyncStatus::Queued,
        'last_sync_attempt_at' => now()->subSeconds(901),
        'updated_at' => now(),
    ]);
    $artist->forceFill(['name' => 'Recently edited'])->save();

    app(ArtistCatalogService::class)->queueSync($artist);

    expect($artist->fresh()->sync_status)->toBe(SyncStatus::Queued)
        ->and($artist->fresh()->last_sync_attempt_at->gt(now()->subSeconds(2)))->toBeTrue();
    Queue::assertPushed(SyncArtistCatalogJob::class, 1);
});

it('supersedes a stale queued job whose unique lock outlived catalog recovery', function () {
    Cache::setDefaultDriver('array');
    Queue::fake();
    artistSyncProvider();
    $artist = Artist::factory()->create([
        'sync_status' => SyncStatus::Queued,
        'last_sync_attempt_at' => now()->subSeconds(901),
    ]);
    $oldJob = new SyncArtistCatalogJob($artist->id, generation: 'old-generation');
    Cache::put('music:artist-generation:'.$artist->id, 'old-generation', 2000);
    $uniqueLocks = new UniqueLock(Cache::store());
    expect($uniqueLocks->acquire($oldJob))->toBeTrue();

    app(ArtistCatalogService::class)->queueSync($artist);

    Queue::assertPushed(SyncArtistCatalogJob::class, fn (SyncArtistCatalogJob $job): bool => $job->generation !== 'old-generation');
    expect(Cache::get('music:artist-generation:'.$artist->id))->not->toBe('old-generation');
});

it('ignores failed callbacks from superseded job generations', function () {
    Cache::setDefaultDriver('array');
    $artist = Artist::factory()->create(['sync_status' => SyncStatus::Queued]);
    Cache::put('music:artist-generation:'.$artist->id, 'new-generation', 1200);

    (new SyncArtistCatalogJob($artist->id, generation: 'old-generation'))->failed(new RuntimeException('old failure'));

    expect($artist->fresh()->sync_status)->toBe(SyncStatus::Queued)
        ->and($artist->fresh()->last_sync_error)->toBeNull();
});

it('stores a complete empty catalog as fresh', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $provider->releaseResults = new ReleaseCollectionData([]);
    $artist = Artist::factory()->create();

    $result = app(ArtistSyncService::class)->sync($artist);

    expect([$result->inserted, $result->updated, $result->skipped])->toBe([0, 0, 0])
        ->and($artist->fresh()->catalog_synced_at)->not->toBeNull()
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Idle);
});

it('fetches missing release details once and caches them by metadata timestamp', function () {
    Cache::setDefaultDriver('array');
    config(['music.metadata_ttl' => 60]);
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create();
    $provider->releaseResults = new ReleaseCollectionData([
        new ReleaseData('yt-needs-details', 'Summary Title', ReleaseType::Unknown),
    ]);
    $provider->releaseDetails['yt-needs-details'] = new ReleaseData(
        'yt-needs-details',
        'Detail Title',
        ReleaseType::Album,
        'Album',
        2001,
        '2001-03-04',
        'https://example.test/detail.jpg',
        'https://example.test/detail',
        [new ArtistData('yt-detail-collaborator', 'Detail Collaborator')],
    );
    $service = app(ArtistSyncService::class);

    $service->sync($artist, true);
    $release = Release::query()->where('youtube_music_id', 'yt-needs-details')->firstOrFail();
    $service->sync($artist, true);
    $release->refresh();
    $firstDetailsTimestamp = $release->metadata_synced_at->toDateTimeString();
    $provider->releaseDetails['yt-needs-details'] = new ReleaseData(
        'yt-needs-details',
        'Detail Title',
        ReleaseType::Album,
        'Album',
        2002,
        '2002-04-05',
        'https://example.test/detail.jpg',
        'https://example.test/detail',
        [new ArtistData('yt-detail-collaborator', 'Detail Collaborator')],
    );
    $this->travel(61)->seconds();
    $service->sync($artist, true);
    $release->refresh();

    expect($provider->detailCalls)->toBe(2)
        ->and($release->title)->toBe('Summary Title')
        ->and($release->type)->toBe(ReleaseType::Album)
        ->and($release->release_year)->toBe(2002)
        ->and($release->metadata_synced_at->toDateTimeString())->not->toBe($firstDetailsTimestamp)
        ->and($release->metadata_synced_at)->not->toBeNull()
        ->and($release->artists()->pluck('artists.youtube_music_id')->all())->toContain('yt-detail-collaborator');
});

it('shares one deadline across all release-detail requests and rolls back catalog writes', function () {
    Cache::setDefaultDriver('array');
    config(['music.request_budget_seconds' => 1]);
    $provider = artistSyncProvider();
    $provider->releaseResults = new ReleaseCollectionData([
        new ReleaseData('yt-budget-one', 'One', ReleaseType::Unknown),
        new ReleaseData('yt-budget-two', 'Two', ReleaseType::Unknown),
    ]);
    $provider->releaseDetails = [
        'yt-budget-one' => new ReleaseData('yt-budget-one', 'One', ReleaseType::Album, releaseYear: 2001),
        'yt-budget-two' => new ReleaseData('yt-budget-two', 'Two', ReleaseType::Album, releaseYear: 2002),
    ];
    $provider->beforeDetail = static fn () => usleep(550_000);
    $artist = Artist::factory()->create();

    expect(fn () => app(ArtistSyncService::class)->sync($artist, true))
        ->toThrow(RuntimeException::class, 'deadline exceeded');

    expect($provider->detailCalls)->toBe(2)
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Failed);
    $this->assertDatabaseMissing('releases', ['youtube_music_id' => 'yt-budget-one']);
    $this->assertDatabaseMissing('releases', ['youtube_music_id' => 'yt-budget-two']);
});

it('keeps committed result successful when deadline passes after commit', function () {
    Cache::setDefaultDriver('array');
    config(['music.request_budget_seconds' => 1]);
    $provider = artistSyncProvider();
    $provider->releaseResults = new ReleaseCollectionData([]);
    $artist = Artist::factory()->create();
    $delayedCommit = false;
    $restoreStatement = 'PRAGMA busy_timeout = '.(int) DB::selectOne('PRAGMA busy_timeout')->timeout;
    $restorationFailed = false;
    Event::listen(TransactionCommitted::class, function () use (&$delayedCommit): void {
        if (! $delayedCommit) {
            $delayedCommit = true;
            usleep(1_050_000);
        }
    });
    DB::listen(function (QueryExecuted $query) use ($restoreStatement, &$restorationFailed): void {
        if (! $restorationFailed && $query->sql === $restoreStatement) {
            $restorationFailed = true;
            throw new RuntimeException('Simulated cleanup failure after committed transaction.');
        }
    });

    $result = app(ArtistSyncService::class)->sync($artist, force: true);

    expect($delayedCommit)->toBeTrue()
        ->and($restorationFailed)->toBeTrue()
        ->and($result->deferred)->toBeFalse()
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Idle)
        ->and($artist->fresh()->catalog_synced_at)->not->toBeNull();
});

it('uses detail credit order, preserves omitted credits, and follows later provider order', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create(['youtube_music_id' => 'yt-primary']);
    $provider->releaseResults = new ReleaseCollectionData([
        new ReleaseData('yt-ordered-release', 'Release', ReleaseType::Unknown, artists: [
            new ArtistData('yt-primary', 'Primary'),
        ]),
    ]);
    $provider->releaseDetails['yt-ordered-release'] = new ReleaseData(
        'yt-ordered-release', 'Release', ReleaseType::Album, releaseYear: 2000, artists: [
            new ArtistData('yt-primary', 'Primary'),
            new ArtistData('yt-detail-credit', 'Detail Credit'),
        ],
    );
    $service = app(ArtistSyncService::class);
    $service->sync($artist, true);
    $release = Release::query()->where('youtube_music_id', 'yt-ordered-release')->firstOrFail();
    expect($release->artists()->orderByPivot('position')->pluck('artists.youtube_music_id')->all())
        ->toBe(['yt-primary', 'yt-detail-credit']);
    $omittedCredit = Artist::factory()->create(['youtube_music_id' => 'yt-previous-credit']);
    $release->artists()->attach($omittedCredit->id, ['position' => 2]);

    $provider->releaseResults = new ReleaseCollectionData([
        new ReleaseData('yt-ordered-release', 'Release', ReleaseType::Album, releaseYear: 2000, artists: [
            new ArtistData('yt-detail-credit', 'Detail Credit'),
            new ArtistData('yt-primary', 'Primary'),
        ]),
    ]);
    $service->sync($artist, true);

    expect($release->artists()->orderByPivot('position')->pluck('artists.youtube_music_id')->all())
        ->toBe(['yt-detail-credit', 'yt-primary', 'yt-previous-credit']);
    expect($release->artists()->whereKey($omittedCredit->id)->first()->pivot->position)->toBe(2);
});

it('unions summary-only credits after details and appends primary after preserved positions', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $primary = Artist::factory()->create(['youtube_music_id' => 'yt-unioned-primary']);
    $release = Release::factory()->create([
        'youtube_music_id' => 'yt-unioned-release',
        'type' => ReleaseType::Unknown,
    ]);
    $existing = Artist::factory()->create(['youtube_music_id' => 'yt-existing-position']);
    $primary->releases()->attach($release->id, ['position' => 1]);
    $release->artists()->attach($existing->id, ['position' => 3]);
    $provider->releaseResults = new ReleaseCollectionData([
        new ReleaseData('yt-unioned-release', 'Release', ReleaseType::Unknown, artists: [
            new ArtistData('yt-credit-a', 'A'),
            new ArtistData('yt-summary-only-b', 'B'),
        ]),
    ]);
    $provider->releaseDetails['yt-unioned-release'] = new ReleaseData(
        'yt-unioned-release', 'Release', ReleaseType::Album, releaseYear: 2000, artists: [
            new ArtistData('yt-credit-a', 'A'),
            new ArtistData('yt-detail-only-c', 'C'),
        ],
    );

    app(ArtistSyncService::class)->sync($primary, force: true);

    expect($release->artists()->orderByPivot('position')->pluck('artists.youtube_music_id')->all())
        ->toBe(['yt-credit-a', 'yt-detail-only-c', 'yt-summary-only-b', 'yt-existing-position', 'yt-unioned-primary'])
        ->and($release->artists()->where('youtube_music_id', 'yt-unioned-primary')->first()->pivot->position)->toBe(4);
});

it('upserts releases while preserving omitted metadata, collaborative credits, and personal ratings', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create(['catalog_synced_at' => now()->subDay()]);
    $collaborator = Artist::factory()->create(['youtube_music_id' => 'yt-collaborator', 'name' => 'Known Collaborator']);
    $release = Release::factory()->create([
        'youtube_music_id' => 'yt-release',
        'title' => 'Known Title',
        'type' => ReleaseType::Album,
        'release_year' => 1999,
        'thumbnail_url' => 'https://example.test/known.jpg',
        'source_url' => 'https://example.test/known',
        'metadata_synced_at' => now()->subDay(),
    ]);
    $artist->releases()->attach($release->id, ['position' => 0]);
    $release->artists()->attach($collaborator->id, ['position' => 1]);
    UserRelease::factory()->create([
        'release_id' => $release->id,
        'status' => UserReleaseStatus::Listened,
        'rating' => 5,
        'notes' => 'Personal note',
    ]);
    $provider->releaseResults = new ReleaseCollectionData([
        new ReleaseData('yt-release', 'Updated Title', ReleaseType::Unknown),
    ]);

    $result = app(ArtistSyncService::class)->sync($artist, true);
    $release->refresh();

    expect([$result->inserted, $result->updated, $result->skipped])->toBe([0, 1, 0])
        ->and($release->title)->toBe('Updated Title')
        ->and($release->type)->toBe(ReleaseType::Album)
        ->and($release->release_year)->toBe(1999)
        ->and($release->thumbnail_url)->toBe('https://example.test/known.jpg')
        ->and($release->metadata_synced_at->toDateTimeString())->toBe(now()->subDay()->toDateTimeString())
        ->and($release->artists()->pluck('artists.id')->all())->toContain($collaborator->id)
        ->and(UserRelease::query()->where('release_id', $release->id)->value('rating'))->toBe(5)
        ->and(UserRelease::query()->where('release_id', $release->id)->value('notes'))->toBe('Personal note');
});

it('counts a newly discovered collaborative credit as an updated release', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create();
    $release = Release::factory()->create([
        'youtube_music_id' => 'yt-credit-update',
        'title' => 'Unchanged Title',
        'type' => ReleaseType::Album,
        'release_year' => 1995,
    ]);
    $artist->releases()->attach($release->id, ['position' => 0]);
    $provider->releaseResults = new ReleaseCollectionData([
        new ReleaseData('yt-credit-update', 'Unchanged Title', ReleaseType::Album, artists: [
            new ArtistData('yt-new-collaborator', 'Collaborator'),
        ]),
    ]);

    $result = app(ArtistSyncService::class)->sync($artist, true);

    expect([$result->inserted, $result->updated, $result->skipped])->toBe([0, 1, 0]);
    $this->assertDatabaseHas('artists', ['youtube_music_id' => 'yt-new-collaborator']);
    $this->assertDatabaseHas('artist_release', ['artist_id' => Artist::query()->where('youtube_music_id', 'yt-new-collaborator')->value('id'), 'release_id' => $release->id]);
});

it('retains the previous catalog and completion timestamp after incomplete collection', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create(['catalog_synced_at' => now()->subDays(2)]);
    $release = Release::factory()->create(['youtube_music_id' => 'yt-existing']);
    $artist->releases()->attach($release->id, ['position' => 0]);
    $provider->releaseResults = new ReleaseCollectionData([
        new ReleaseData('yt-new', 'Partial', ReleaseType::Single),
    ], complete: false);

    expect(fn () => app(ArtistSyncService::class)->sync($artist, true))
        ->toThrow(UnexpectedValueException::class);

    expect($artist->fresh()->catalog_synced_at->toDateTimeString())->toBe(now()->subDays(2)->toDateTimeString())
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Failed)
        ->and($artist->fresh()->last_sync_error)->not->toBeEmpty();
    $this->assertDatabaseMissing('releases', ['youtube_music_id' => 'yt-new']);
    $this->assertDatabaseHas('artist_release', ['artist_id' => $artist->id, 'release_id' => $release->id]);
});

it('keeps prior catalog fresh timestamp and sanitizes provider failures', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create(['catalog_synced_at' => now()->subDays(3)]);
    $provider->failure = new RuntimeException('sensitive upstream response');

    expect(fn () => app(ArtistSyncService::class)->sync($artist, true))
        ->toThrow(RuntimeException::class);

    expect($artist->fresh()->catalog_synced_at->toDateTimeString())->toBe(now()->subDays(3)->toDateTimeString())
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Failed)
        ->and($artist->fresh()->last_sync_error)->not->toContain('sensitive upstream response');
});

it('rejects malformed collaborator data before writing any catalog records', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create();
    $provider->releaseResults = new ReleaseCollectionData([
        new ReleaseData('yt-invalid-credit-release', 'Release', ReleaseType::Album, artists: [new ArtistData('', 'Unknown')]),
    ]);

    expect(fn () => app(ArtistSyncService::class)->sync($artist, true))
        ->toThrow(UnexpectedValueException::class);

    $this->assertDatabaseMissing('releases', ['youtube_music_id' => 'yt-invalid-credit-release']);
    $this->assertDatabaseMissing('artists', ['youtube_music_id' => '']);
});

it('runs the command through the same synchronizer and reports its counts', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $artist = Artist::factory()->create();
    $provider->releaseResults = new ReleaseCollectionData([
        new ReleaseData('yt-command-release', 'Command Release', ReleaseType::Album),
    ]);
    $provider->releaseDetails['yt-command-release'] = new ReleaseData(
        'yt-command-release',
        'Command Release',
        ReleaseType::Album,
        releaseYear: 2020,
    );

    $this->artisan('music:sync-artist', ['artist' => $artist->id, '--force' => true])
        ->expectsOutput('Inserted: 1; updated: 0; skipped: 0.')
        ->assertSuccessful();

    $this->assertDatabaseHas('releases', ['youtube_music_id' => 'yt-command-release']);
});

it('returns a failure code from the command when provider synchronization fails', function () {
    Cache::setDefaultDriver('array');
    $provider = artistSyncProvider();
    $provider->failure = new RuntimeException('sensitive provider response');
    $artist = Artist::factory()->create();

    $this->artisan('music:sync-artist', ['artist' => $artist->id, '--force' => true])
        ->expectsOutput('Artist catalog synchronization failed.')
        ->assertExitCode(1);

    expect($artist->fresh()->sync_status)->toBe(SyncStatus::Failed)
        ->and($artist->fresh()->last_sync_error)->not->toContain('sensitive provider response');
});
