<?php

use App\Enums\UserReleaseStatus;
use App\Models\Artist;
use App\Models\Release;
use App\Models\User;
use App\Models\UserRelease;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('generates ULIDs for users and music records', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();
    $release = Release::factory()->create();
    $userRelease = UserRelease::factory()->for($user)->for($release)->create();

    foreach ([$user->id, $artist->id, $release->id, $userRelease->id] as $id) {
        expect(Str::isUlid(Str::upper($id)))->toBeTrue();
    }
});

it('enforces unique YouTube Music ids and association pairs', function () {
    $artist = Artist::factory()->create(['youtube_music_id' => 'artist-1']);
    $release = Release::factory()->create(['youtube_music_id' => 'release-1']);
    $user = User::factory()->create();
    UserRelease::factory()->for($user)->for($release)->create();

    expect(fn () => Artist::factory()->create(['youtube_music_id' => 'artist-1']))
        ->toThrow(QueryException::class)
        ->and(fn () => Release::factory()->create(['youtube_music_id' => 'release-1']))
        ->toThrow(QueryException::class)
        ->and(fn () => UserRelease::factory()->for($user)->for($release)->create())
        ->toThrow(QueryException::class);
});

it('stores exact catalog synchronization metadata on artists and releases', function () {
    $artist = Artist::factory()->create([
        'last_sync_attempt_at' => '2025-01-02 03:04:05',
        'last_sync_error' => 'Temporary provider failure',
    ]);
    $release = Release::factory()->create([
        'metadata_synced_at' => '2025-01-03 04:05:06',
    ]);

    expect($artist->last_sync_attempt_at->toDateTimeString())->toBe('2025-01-02 03:04:05')
        ->and($artist->last_sync_error)->toBe('Temporary provider failure')
        ->and($release->metadata_synced_at->toDateTimeString())->toBe('2025-01-03 04:05:06');

    expect(Schema::hasColumn('artists', 'external_id'))->toBeFalse()
        ->and(Schema::hasColumn('artists', 'discovered_at'))->toBeFalse()
        ->and(Schema::hasColumn('releases', 'external_id'))->toBeFalse();
});

it('retains collaborative credits when attaching another artist', function () {
    $release = Release::factory()->create();
    $firstArtist = Artist::factory()->create();
    $secondArtist = Artist::factory()->create();

    $release->artists()->attach($firstArtist->id, ['position' => 0]);
    $release->artists()->syncWithoutDetaching([$secondArtist->id => ['position' => 1]]);

    expect($release->artists()->orderByPivot('position')->pluck('artists.id')->all())
        ->toBe([$firstArtist->id, $secondArtist->id]);

    expect(fn () => $release->artists()->attach($firstArtist->id, ['position' => 0]))
        ->toThrow(QueryException::class);
});

it('accepts a listened release without a rating', function () {
    $userRelease = UserRelease::factory()->create([
        'status' => UserReleaseStatus::Listened,
        'rating' => null,
        'listened_at' => null,
    ]);

    expect($userRelease->status)->toBe(UserReleaseStatus::Listened)
        ->and($userRelease->rating)->toBeNull();
});

it('rejects out of range or fractional ratings', function (mixed $rating) {
    $user = User::factory()->create();
    $release = Release::factory()->create();

    expect(fn () => UserRelease::factory()->for($user)->for($release)->create([
        'status' => UserReleaseStatus::Listened,
        'rating' => $rating,
    ]))->toThrow(QueryException::class);
})->with([
    'below range' => 0,
    'above range' => 6,
    'fractional' => 1.5,
]);

it('rejects an invalid user release status', function () {
    $user = User::factory()->create();
    $release = Release::factory()->create();

    expect(fn () => DB::table('user_releases')->insert([
        'id' => (string) Str::ulid(),
        'user_id' => $user->id,
        'release_id' => $release->id,
        'status' => 'archived',
    ]))->toThrow(QueryException::class);
});

it('rejects invalid catalog enum values in the database', function () {
    expect(fn () => DB::table('artists')->insert([
        'id' => (string) Str::ulid(),
        'youtube_music_id' => 'artist-invalid-state',
        'name' => 'Artist',
        'sync_status' => 'complete',
    ]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('releases')->insert([
            'id' => (string) Str::ulid(),
            'youtube_music_id' => 'release-invalid-type',
            'title' => 'Release',
            'type' => 'compilation',
        ]))->toThrow(QueryException::class);
});

it('allows ratings and listened dates only for listened releases', function () {
    $user = User::factory()->create();
    $release = Release::factory()->create();

    expect(fn () => UserRelease::factory()->for($user)->for($release)->create([
        'status' => UserReleaseStatus::Listening,
        'rating' => 4,
    ]))->toThrow(QueryException::class);
});

it('removes personal catalog data without deleting the shared release', function () {
    $user = User::factory()->create();
    $release = Release::factory()->create();
    $userRelease = UserRelease::factory()->for($user)->for($release)->create();

    $userRelease->delete();

    expect(UserRelease::query()->find($userRelease->id))->toBeNull()
        ->and(Release::query()->find($release->id))->not->toBeNull();
});
