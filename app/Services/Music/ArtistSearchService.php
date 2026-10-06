<?php

namespace App\Services\Music;

use App\Models\Artist;
use App\Music\Contracts\MusicCatalogProvider;
use App\Music\Data\ArtistData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArtistSearchService
{
    public function __construct(private MusicCatalogProvider $provider) {}

    /** @return Collection<int, Artist> */
    public function search(string $query): Collection
    {
        $query = Str::squish($query);
        $key = hash('sha256', Str::lower($query));
        $cacheKey = 'music:artist-search:'.hash('sha256', implode('|', [
            config('music.youtube_music.hl'),
            config('music.youtube_music.gl'),
            $key,
        ]));
        $ttl = max(1, (int) config('music.search_ttl', 3600));
        $artists = Cache::remember($cacheKey, $ttl, function () use ($query): array {
            return array_map(static function (ArtistData $artist): array {
                if (trim($artist->externalId) === '' || trim($artist->name) === '') {
                    throw new \UnexpectedValueException('Provider returned invalid artist search data.');
                }

                return [
                    'external_id' => $artist->externalId,
                    'name' => $artist->name,
                    'thumbnail_url' => $artist->thumbnailUrl,
                ];
            }, $this->provider->searchArtists($query));
        });

        foreach ($artists as $artistData) {
            if (! is_array($artistData)
                || ! is_string($artistData['external_id'] ?? null)
                || trim($artistData['external_id']) === ''
                || ! is_string($artistData['name'] ?? null)
                || trim($artistData['name']) === ''
                || (! is_string($artistData['thumbnail_url'] ?? null) && ($artistData['thumbnail_url'] ?? null) !== null)) {
                throw new \UnexpectedValueException('Cached artist search data is invalid.');
            }
        }

        return DB::transaction(function () use ($artists): Collection {
            $results = collect();

            foreach ($artists as $artistData) {
                $artist = Artist::query()->firstOrNew(['youtube_music_id' => $artistData['external_id']]);
                $artist->name = $artistData['name'];
                if ($artistData['thumbnail_url'] !== null) {
                    $artist->thumbnail_url = $artistData['thumbnail_url'];
                }
                $artist->save();
                $results->push($artist);
            }

            return $results;
        }, attempts: 3);
    }
}
