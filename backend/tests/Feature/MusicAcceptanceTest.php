<?php

use App\Enums\ReleaseType;
use App\Enums\SyncStatus;
use App\Enums\UserReleaseStatus;
use App\Jobs\SyncArtistCatalogJob;
use App\Models\Artist;
use App\Models\Release;
use App\Models\User;
use App\Models\UserRelease;
use App\Music\Exceptions\MusicProviderResponseException;
use App\Services\Music\ArtistSyncService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function musicAcceptanceFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/youtube-music/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
}

function fakeMusicAcceptanceProvider(bool $refreshAddsRelease = false): void
{
    Http::preventStrayRequests();
    $continuationRequests = 0;

    Http::fake(function (Request $request) use ($refreshAddsRelease, &$continuationRequests) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $browseId = data_get($request->data(), 'browseId');

        return match (true) {
            $path === '/youtubei/v1/search' && data_get($request->data(), 'query') === 'Froid' => Http::response(musicAcceptanceFixture('acceptance-search')),
            $path === '/youtubei/v1/browse' && $browseId === 'UCfroidselected' => Http::response(musicAcceptanceFixture('acceptance-artist')),
            $path === '/youtubei/v1/browse'
                && $browseId === 'MPREb_acceptance_albums'
                && data_get($request->data(), 'params') === 'acceptance-section-params'
                && ! str_contains($request->url(), 'continuation=') => Http::response(musicAcceptanceFixture('acceptance-grid-page-one')),
            $path === '/youtubei/v1/browse'
                && $browseId === 'MPREb_acceptance_albums'
                && str_contains($request->url(), 'ctoken=acceptance-next-page')
                && str_contains($request->url(), 'continuation=acceptance-next-page')
                && ! array_key_exists('continuation', $request->data()) => Http::response(musicAcceptanceFixture(++$continuationRequests === 1 || ! $refreshAddsRelease
                    ? 'acceptance-grid-continuation-empty'
                    : 'acceptance-grid-continuation-refresh')),
            $path === '/youtubei/v1/browse' && $browseId === 'MPREb_Tr9YVfRfip1' => Http::response(musicAcceptanceFixture('upstream-release')),
            default => throw new LogicException("Unexpected provider request: {$request->method()} {$request->url()}"),
        };
    });
}

it('searches Froid candidates, syncs selected catalog through real provider and preserves personal data on refresh', function () {
    $this->travelTo('2026-10-06 12:00:00');
    Cache::setDefaultDriver('array');
    Queue::fake([SyncArtistCatalogJob::class]);
    fakeMusicAcceptanceProvider(refreshAddsRelease: true);
    $user = User::factory()->create();
    $collaborator = Artist::factory()->create([
        'youtube_music_id' => 'UCnAcxgRZ065f_eXK1o85c1w',
        'name' => 'Existing collaborator',
    ]);

    $search = $this->actingAs($user)->getJson('/api/v1/artists/search?q=Froid')->assertOk();

    $search->assertJsonCount(2, 'data');
    $decoy = Artist::query()->where('youtube_music_id', 'UCfroiddecoy')->firstOrFail();
    $artist = Artist::query()->where('youtube_music_id', 'UCfroidselected')->firstOrFail();
    expect($decoy->id)->not->toBe($artist->id)
        ->and($artist->catalog_synced_at)->toBeNull();

    $existingRelease = Release::factory()->create([
        'youtube_music_id' => 'MPREb_acceptance_existing',
        'title' => 'Previous title',
        'type' => ReleaseType::Album,
        'release_year' => 2020,
        'metadata_synced_at' => now(),
    ]);
    $artist->releases()->attach($existingRelease->id, ['position' => 0]);
    $existingRelease->artists()->attach($collaborator->id, ['position' => 1]);

    $this->getJson("/api/v1/artists/{$artist->id}/releases")
        ->assertStatus(202)
        ->assertJsonPath('meta.sync.pending', true);
    Queue::assertPushed(SyncArtistCatalogJob::class, fn (SyncArtistCatalogJob $job): bool => $job->artistId === $artist->id && ! $job->force);

    $initialJob = Queue::pushed(SyncArtistCatalogJob::class)->first();
    expect($initialJob)->toBeInstanceOf(SyncArtistCatalogJob::class)
        ->and($initialJob->generation)->not->toBeNull();
    $initialJob->handle(app(ArtistSyncService::class));
    (new UniqueLock(Cache::store()))->release($initialJob);
    $artist->refresh();
    $existingRelease->refresh();
    $this->assertDatabaseHas('artist_release', ['artist_id' => $collaborator->id, 'release_id' => $existingRelease->id]);
    expect($artist->catalog_synced_at)->not->toBeNull()
        ->and($existingRelease->title)->toBe('Acceptance Album');

    $sentAfterSync = Http::recorded()->count();
    $this->getJson("/api/v1/artists/{$artist->id}/releases")
        ->assertOk()
        ->assertJsonPath('meta.sync.pending', false)
        ->assertJsonPath('data.releases.0.id', $existingRelease->id)
        ->assertJsonPath('data.releases.0.artists.1.id', $collaborator->id);
    expect(Http::recorded()->count())->toBe($sentAfterSync);

    $this->putJson('/api/v1/me/releases/'.$existingRelease->id, [
        'status' => UserReleaseStatus::Listened->value,
        'rating' => 5,
        'listened_at' => '2025-01-02',
        'notes' => 'My acceptance note',
    ])->assertOk()->assertJsonPath('data.rating', 5);
    $personalRecord = UserRelease::query()->where('user_id', $user->id)->where('release_id', $existingRelease->id)->firstOrFail();

    $this->postJson("/api/v1/artists/{$artist->id}/sync", ['force' => true])->assertAccepted();
    Queue::assertPushed(SyncArtistCatalogJob::class, fn (SyncArtistCatalogJob $job): bool => $job->artistId === $artist->id && $job->force);
    $refreshJob = Queue::pushed(SyncArtistCatalogJob::class, fn (SyncArtistCatalogJob $job): bool => $job->force)->last();
    expect($refreshJob)->toBeInstanceOf(SyncArtistCatalogJob::class)
        ->and($refreshJob->generation)->not->toBeNull();
    $refreshJob->handle(app(ArtistSyncService::class));
    (new UniqueLock(Cache::store()))->release($refreshJob);
    $personalRecord->refresh();
    $newRelease = Release::query()->where('youtube_music_id', 'MPREb_Tr9YVfRfip1')->firstOrFail();

    expect([$personalRecord->status, $personalRecord->rating, $personalRecord->listened_at->toDateString(), $personalRecord->notes])
        ->toBe([UserReleaseStatus::Listened, 5, '2025-01-02', 'My acceptance note']);
    $this->assertDatabaseHas('artist_release', ['artist_id' => $artist->id, 'release_id' => $newRelease->id]);
    $this->assertDatabaseHas('artist_release', ['artist_id' => $collaborator->id, 'release_id' => $existingRelease->id]);

    $artistReleases = $this->getJson("/api/v1/artists/{$artist->id}/releases?per_page=1")->assertOk();
    $artistReleases->assertJsonCount(1, 'data.releases')->assertJsonPath('meta.per_page', 1);
    expect($artistReleases->json('links.next'))->not->toBeNull();
    $next = $artistReleases->json('links.next');
    parse_str((string) parse_url($next, PHP_URL_QUERY), $nextQuery);
    $this->getJson("/api/v1/artists/{$artist->id}/releases?".http_build_query($nextQuery))
        ->assertOk()
        ->assertJsonCount(1, 'data.releases');

    $filtered = $this->getJson('/api/v1/releases?'.http_build_query(['artist_id' => $artist->id, 'type' => 'album']))
        ->assertOk()
        ->assertJsonCount(2, 'data');
    expect(collect($filtered->json('data'))->pluck('id')->all())
        ->toContain($existingRelease->id, $newRelease->id);
});

it('serves stale local releases and retains freshness timestamp when real provider returns an invalid artist layout', function () {
    $this->travelTo('2026-10-06 12:00:00');
    Cache::setDefaultDriver('array');
    Queue::fake([SyncArtistCatalogJob::class]);
    Http::preventStrayRequests();
    Http::fake([
        'https://music.youtube.com/youtubei/v1/browse?*' => Http::response([]),
    ]);
    $user = User::factory()->create();
    $artist = Artist::factory()->create([
        'youtube_music_id' => 'UCfroidselected',
        'catalog_synced_at' => now()->subHours(7),
    ]);
    $release = Release::factory()->create(['youtube_music_id' => 'MPREb_old-local']);
    $artist->releases()->attach($release->id, ['position' => 0]);
    $previousSync = $artist->catalog_synced_at->toDateTimeString();

    $this->actingAs($user)->getJson("/api/v1/artists/{$artist->id}/releases")
        ->assertOk()
        ->assertJsonPath('meta.sync.stale', true)
        ->assertJsonPath('data.releases.0.id', $release->id);
    Queue::assertPushed(SyncArtistCatalogJob::class);

    $queuedJob = Queue::pushed(SyncArtistCatalogJob::class)->first();
    expect($queuedJob)->toBeInstanceOf(SyncArtistCatalogJob::class)
        ->and($queuedJob->generation)->not->toBeNull();

    expect(fn () => $queuedJob->handle(app(ArtistSyncService::class)))
        ->toThrow(MusicProviderResponseException::class);

    expect($artist->fresh()->catalog_synced_at->toDateTimeString())->toBe($previousSync)
        ->and($artist->fresh()->sync_status)->toBe(SyncStatus::Failed);
    $this->assertDatabaseHas('artist_release', ['artist_id' => $artist->id, 'release_id' => $release->id]);
    $this->assertDatabaseMissing('releases', ['youtube_music_id' => 'MPREb_Tr9YVfRfip1']);
});
