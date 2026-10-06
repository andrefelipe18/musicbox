<?php

use App\Enums\UserReleaseStatus;
use App\Models\Artist;
use App\Models\Release;
use App\Models\User;
use App\Models\UserRelease;
use App\Music\Exceptions\MusicProviderUnavailableException;
use App\Services\Music\ArtistCatalogService;
use App\Services\Music\ArtistSearchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

it('does not call provider-backed search or sync for guests', function () {
    $artist = Artist::factory()->create();

    $this->getJson('/api/v1/artists/search?q=Froid')->assertUnauthorized();
    $this->getJson("/api/v1/artists/{$artist->id}/releases")->assertUnauthorized();
    $this->postJson("/api/v1/artists/{$artist->id}/sync")->assertUnauthorized();
});

it('authenticates artist search and forwards its trimmed query to the service contract', function () {
    $artist = Artist::factory()->create();
    $search = new class($artist)
    {
        public ?string $query = null;

        public function __construct(public Artist $artist) {}

        public function search(string $query): Collection
        {
            $this->query = $query;

            return collect([$this->artist]);
        }
    };
    app()->instance(ArtistSearchService::class, $search);

    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/artists/search?q=%20Froid%20')
        ->assertOk()
        ->assertJsonPath('data.0.id', $artist->id);

    expect($search->query)->toBe('Froid');
});

it('returns a sanitized JSON error when the provider is unavailable', function () {
    config(['app.debug' => true]);
    $search = new class
    {
        public function search(string $query): Collection
        {
            throw new MusicProviderUnavailableException('raw upstream response secret');
        }
    };
    app()->instance(ArtistSearchService::class, $search);

    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/artists/search?q=Froid')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Music provider is temporarily unavailable.')
        ->assertExactJson(['message' => 'Music provider is temporarily unavailable.'])
        ->assertJsonMissing(['message' => 'raw upstream response secret']);
});

it('returns pending and stale artist catalogs and queues explicit sync through the service contract', function () {
    $artist = Artist::factory()->create();
    $catalog = new class($artist)
    {
        public array $state;

        public ?bool $force = null;

        public function __construct(Artist $artist)
        {
            $this->state = ['artist' => $artist, 'releases' => collect(), 'stale' => false, 'pending' => true];
        }

        public function releases(Artist $artist): array
        {
            return $this->state;
        }

        public function queueSync(Artist $artist, bool $force = false): void
        {
            $this->force = $force;
        }
    };
    app()->instance(ArtistCatalogService::class, $catalog);
    $user = User::factory()->create();

    $this->actingAs($user)->getJson("/api/v1/artists/{$artist->id}/releases")
        ->assertStatus(202)
        ->assertJsonPath('meta.sync.pending', true);

    $catalog->state['stale'] = true;
    $this->getJson("/api/v1/artists/{$artist->id}/releases")
        ->assertOk()
        ->assertJsonPath('meta.sync.stale', true);

    $this->postJson("/api/v1/artists/{$artist->id}/sync", ['force' => 'invalid'])->assertUnprocessable();
    expect($catalog->force)->toBeNull();

    $this->postJson("/api/v1/artists/{$artist->id}/sync")->assertStatus(202);
    expect($catalog->force)->toBeTrue();
    $this->postJson("/api/v1/artists/{$artist->id}/sync", ['force' => false])->assertStatus(202);
    expect($catalog->force)->toBeFalse();
});

it('isolates personal release records by authenticated owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $release = Release::factory()->create();
    UserRelease::factory()->create(['user_id' => $other->id, 'release_id' => $release->id]);

    $this->actingAs($owner)->getJson('/api/v1/me/releases')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/me/releases/{$release->id}")->assertNotFound();
    $this->actingAs($owner)->putJson("/api/v1/me/releases/{$release->id}", [
        'status' => UserReleaseStatus::Listened->value,
        'rating' => 5,
        'listened_at' => '2025-01-02',
        'notes' => 'Great',
    ])->assertOk()->assertJsonPath('data.rating', 5);

    $this->assertDatabaseCount('user_releases', 2);
    $this->assertDatabaseHas('user_releases', ['user_id' => $owner->id, 'release_id' => $release->id]);

    $this->deleteJson("/api/v1/me/releases/{$release->id}")->assertOk();
    $this->assertDatabaseCount('user_releases', 1);
    $this->assertDatabaseHas('releases', ['id' => $release->id]);
});

it('treats personal catalog PUT as replacement and rejects invalid rating and date', function () {
    $user = User::factory()->create();
    $release = Release::factory()->create();

    $this->actingAs($user)->putJson("/api/v1/me/releases/{$release->id}", [
        'status' => UserReleaseStatus::Listened->value,
        'rating' => 5,
        'listened_at' => '2025-01-02',
        'notes' => 'Old note',
        'user_id' => User::factory()->create()->id,
    ])->assertOk();
    $this->assertDatabaseHas('user_releases', ['user_id' => $user->id, 'release_id' => $release->id]);

    $this->actingAs($user)->putJson("/api/v1/me/releases/{$release->id}", ['status' => UserReleaseStatus::Listened->value])
        ->assertOk()
        ->assertJsonPath('data.rating', null)
        ->assertJsonPath('data.listened_at', null)
        ->assertJsonPath('data.notes', null);
    $this->assertDatabaseCount('user_releases', 1);

    $this->actingAs($user)->putJson("/api/v1/me/releases/{$release->id}", [
        'status' => UserReleaseStatus::Listened->value,
        'rating' => 1.5,
        'listened_at' => '2025-02-30',
    ])->assertUnprocessable()->assertJsonValidationErrors(['rating', 'listened_at']);
});

it('rejects personal catalog combinations and non-integer JSON ratings', function () {
    $user = User::factory()->create();
    $release = Release::factory()->create();

    $this->actingAs($user)->putJson("/api/v1/me/releases/{$release->id}", [
        'status' => UserReleaseStatus::WantToListen->value,
        'rating' => 4,
    ])->assertUnprocessable()->assertJsonValidationErrors('status');

    $this->putJson("/api/v1/me/releases/{$release->id}", [
        'status' => UserReleaseStatus::Listened->value,
        'rating' => '4',
    ])->assertUnprocessable()->assertJsonValidationErrors('rating');
});

it('rejects unlisted sort expressions and dates after UTC today', function () {
    $user = User::factory()->create();
    $release = Release::factory()->create();

    $this->getJson('/api/v1/releases?sort=title desc;drop table users')->assertUnprocessable();
    $this->actingAs($user)->putJson("/api/v1/me/releases/{$release->id}", [
        'status' => UserReleaseStatus::Listened->value,
        'listened_at' => '2999-01-01',
    ])->assertUnprocessable()->assertJsonValidationErrors('listened_at');
});

it('provides local catalog listings with pagination and internal ULID resources', function () {
    $artist = Artist::factory()->create();
    $release = Release::factory()->create();
    $artist->releases()->attach($release->id, ['position' => 0]);

    $this->getJson('/api/v1/releases?per_page=200')->assertUnprocessable();
    $this->getJson('/api/v1/artists/'.$artist->id)->assertOk()->assertJsonPath('data.id', $artist->id);
    $response = $this->getJson('/api/v1/releases')->assertOk();
    $response->assertJsonPath('data.0.id', $release->id)->assertJsonStructure(['links', 'meta']);

    expect($artist->id)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/i');
});

it('preserves validated release filters in pagination links', function () {
    $artist = Artist::factory()->create();
    $user = User::factory()->create();
    foreach (range(1, 21) as $index) {
        $release = Release::factory()->create([
            'title' => sprintf('Filtered Album %02d', $index),
            'type' => 'album',
        ]);
        $artist->releases()->attach($release->id, ['position' => $index]);
        UserRelease::factory()->create([
            'user_id' => $user->id,
            'release_id' => $release->id,
            'status' => UserReleaseStatus::Listened,
            'rating' => 5,
        ]);
    }
    $query = http_build_query([
        'artist_id' => $artist->id,
        'type' => 'album',
        'status' => 'listened',
        'rating' => 5,
        'sort' => 'title',
        'direction' => 'asc',
        'per_page' => 20,
    ]);

    $this->actingAs($user)->getJson('/api/v1/releases?'.$query)->assertOk()
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.current_page', 1);
    $next = $this->getJson('/api/v1/releases?'.$query)->json('links.next');
    parse_str((string) parse_url($next, PHP_URL_QUERY), $nextQuery);
    expect($nextQuery)->toMatchArray([
        'artist_id' => $artist->id,
        'type' => 'album',
        'status' => 'listened',
        'rating' => '5',
        'sort' => 'title',
        'direction' => 'asc',
        'per_page' => '20',
        'page' => '2',
    ]);

    $this->getJson('/api/v1/releases?'.http_build_query($nextQuery))->assertOk()->assertJsonCount(1, 'data');

    $personal = $this->getJson('/api/v1/me/releases?'.$query)->assertOk()->assertJsonCount(20, 'data');
    parse_str((string) parse_url($personal->json('links.next'), PHP_URL_QUERY), $personalNextQuery);
    expect($personalNextQuery)->toMatchArray([
        'artist_id' => $artist->id,
        'type' => 'album',
        'status' => 'listened',
        'rating' => '5',
        'sort' => 'title',
        'direction' => 'asc',
        'per_page' => '20',
        'page' => '2',
    ]);
    $this->getJson('/api/v1/me/releases?'.http_build_query($personalNextQuery))->assertOk()->assertJsonCount(1, 'data');
});

it('paginates artist catalogs locally with collaborators and validated filters', function () {
    $artist = Artist::factory()->create();
    $collaborator = Artist::factory()->create();
    foreach (range(1, 21) as $index) {
        $release = Release::factory()->create([
            'title' => sprintf('Discography Album %02d', $index),
            'type' => 'album',
        ]);
        $artist->releases()->attach($release->id, ['position' => $index]);
        $release->artists()->attach($collaborator->id, ['position' => 2]);
    }
    $catalog = new class($artist)
    {
        public array $state;

        public function __construct(Artist $artist)
        {
            $this->state = ['artist' => $artist, 'stale' => true, 'pending' => true];
        }

        public function releases(Artist $artist): array
        {
            return $this->state;
        }

        public function queueSync(Artist $artist, bool $force = false): void {}
    };
    app()->instance(ArtistCatalogService::class, $catalog);
    $user = User::factory()->create();
    $query = http_build_query(['per_page' => 20, 'type' => 'album', 'sort' => 'title', 'direction' => 'asc']);

    $first = $this->actingAs($user)->getJson("/api/v1/artists/{$artist->id}/releases?{$query}")->assertOk();
    $first->assertJsonCount(20, 'data.releases')
        ->assertJsonPath('meta.sync.stale', true)
        ->assertJsonPath('data.releases.0.artists.1.id', $collaborator->id);
    parse_str((string) parse_url($first->json('links.next'), PHP_URL_QUERY), $nextQuery);
    expect($nextQuery)->toMatchArray(['per_page' => '20', 'type' => 'album', 'sort' => 'title', 'direction' => 'asc', 'page' => '2']);

    $this->getJson("/api/v1/artists/{$artist->id}/releases?".http_build_query($nextQuery))
        ->assertOk()->assertJsonCount(1, 'data.releases');
    $this->getJson("/api/v1/artists/{$artist->id}/releases?per_page=101")->assertUnprocessable();
    $this->getJson("/api/v1/artists/{$artist->id}/releases?sort=secret")->assertUnprocessable();
});

it('retries concurrent personal catalog writes across SQLite connections', function () {
    $databasePath = storage_path('framework/testing/music-catalog-'.Str::ulid().'.sqlite');
    if (! is_dir(dirname($databasePath))) {
        mkdir(dirname($databasePath), 0777, true);
    }
    touch($databasePath);
    $barrierPath = $databasePath.'.barrier';
    mkdir($barrierPath);
    $originalConnection = config('database.default');
    $sqlite = config('database.connections.sqlite');
    config([
        'database.connections.catalog_test_a' => array_replace($sqlite, ['database' => $databasePath, 'busy_timeout' => 0]),
        'database.connections.catalog_test_b' => array_replace($sqlite, ['database' => $databasePath, 'busy_timeout' => 0]),
        'database.default' => 'catalog_test_a',
    ]);
    DB::purge('catalog_test_a');
    DB::purge('catalog_test_b');

    try {
        Schema::connection('catalog_test_a')->create('users', function ($table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::connection('catalog_test_a')->create('releases', function ($table): void {
            $table->ulid('id')->primary();
            $table->string('youtube_music_id')->unique();
            $table->string('title');
            $table->string('type');
            $table->timestamps();
        });
        Schema::connection('catalog_test_a')->create('user_releases', function ($table): void {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('release_id');
            $table->string('status');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->date('listened_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'release_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('release_id')->references('id')->on('releases')->cascadeOnDelete();
        });
        $user = User::factory()->create();
        $release = Release::factory()->create();
        $userId = $user->id;
        $releaseId = $release->id;
        $workerCode = static function (string $worker) use ($databasePath, $barrierPath, $userId, $releaseId): string {
            return '$root = '.var_export(base_path(), true).';'
                .'require $root."/vendor/autoload.php";'
                .'$app = require $root."/bootstrap/app.php";'
                .'$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();'
                .'config(["database.default" => "sqlite", "database.connections.sqlite.database" => '.var_export($databasePath, true).', "database.connections.sqlite.busy_timeout" => 0]);'
                .'\\Illuminate\\Support\\Facades\\DB::purge("sqlite");'
                .'$ready = '.var_export($barrierPath.'/ready-'.$worker, true).';'
                .'$go = '.var_export($barrierPath.'/go', true).';'
                .'\\App\\Models\\UserRelease::creating(static function () use ($ready, $go): void { '
                .'file_put_contents($ready, "ready"); '
                .'$deadline = microtime(true) + 8; '
                .'while (! is_file($go) && microtime(true) < $deadline) { usleep(5000); } '
                .'if (! is_file($go)) { throw new \\RuntimeException("Barrier timeout."); } '
                .'});'
                .'$user = new \\App\\Models\\User; $user->setRawAttributes(["id" => '.var_export($userId, true).'], true);'
                .'$release = new \\App\\Models\\Release; $release->setRawAttributes(["id" => '.var_export($releaseId, true).'], true);'
                .'app(\\App\\Services\\Music\\UserCatalogService::class)->replace($user, $release, '
                .'["status" => "listened", "rating" => '.($worker === 'one' ? '4' : '5').', "notes" => '.var_export($worker, true).']);';
        };
        config(['database.default' => $originalConnection]);
        DB::purge('catalog_test_a');
        DB::purge('catalog_test_b');
        $workers = array_map(
            fn (string $worker): Process => new Process([PHP_BINARY, '-r', $workerCode($worker)], base_path(), timeout: 15),
            ['one', 'two'],
        );
        foreach ($workers as $worker) {
            $worker->start();
        }
        $deadline = microtime(true) + 10;
        while ((! is_file($barrierPath.'/ready-one') || ! is_file($barrierPath.'/ready-two')) && microtime(true) < $deadline) {
            usleep(10_000);
        }
        $bothReachedInsert = is_file($barrierPath.'/ready-one') && is_file($barrierPath.'/ready-two');
        touch($barrierPath.'/go');
        foreach ($workers as $worker) {
            $worker->wait();
        }

        expect($bothReachedInsert)->toBeTrue();
        expect(array_map(fn (Process $worker): int => $worker->getExitCode() ?? -1, $workers))
            ->toBe([0, 0], implode("\n", array_map(fn (Process $worker): string => $worker->getErrorOutput(), $workers)));

        config([
            'database.connections.catalog_test_a' => array_replace($sqlite, ['database' => $databasePath]),
            'database.default' => 'catalog_test_a',
        ]);
        DB::purge('catalog_test_a');
        $entries = DB::connection('catalog_test_a')->table('user_releases')->get();
        expect($entries)->toHaveCount(1);
        expect([$entries->first()->rating, $entries->first()->notes])->toBeIn([
            [4, 'one'],
            [5, 'two'],
        ]);
    } finally {
        config(['database.default' => $originalConnection]);
        DB::purge('catalog_test_a');
        DB::purge('catalog_test_b');
        if (is_dir($barrierPath)) {
            foreach (glob($barrierPath.'/*') ?: [] as $barrierFile) {
                unlink($barrierFile);
            }
            rmdir($barrierPath);
        }
        if (is_file($databasePath)) {
            unlink($databasePath);
        }
        foreach ([$databasePath.'-shm', $databasePath.'-wal'] as $databaseSidecar) {
            if (is_file($databaseSidecar)) {
                unlink($databaseSidecar);
            }
        }
    }
});
