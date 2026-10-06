<?php

namespace App\Services\Music;

use App\Http\Resources\Api\V1\ArtistResource;
use App\Http\Resources\Api\V1\ReleaseResource;
use App\Models\Artist;
use App\Models\Release;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Assembles the artist catalog page payload: the artist, its paginated releases and the sync state.
 */
class ArtistReleasePageService
{
    public function __construct(private ReleaseListService $releases) {}

    /**
     * @param  array{artist?: Artist, releases?: Collection<int, Artist>, stale?: bool, pending?: bool}  $catalog
     * @param  array{type?: string, sort?: string, direction?: string, per_page?: int}  $filters
     * @return array{status: int, body: array<string, mixed>}
     */
    public function build(Artist $artist, array $catalog, array $filters): array
    {
        $stale = (bool) ($catalog['stale'] ?? false);
        $pending = (bool) ($catalog['pending'] ?? false);
        $resolved = $catalog['artist'] ?? $artist;

        $page = $this->payload($this->releases->artistReleases($resolved, $filters));
        $page['meta']['sync'] = ['stale' => $stale, 'pending' => $pending];

        return [
            'status' => $pending && ! $stale ? 202 : 200,
            'body' => [
                'data' => [
                    'artist' => (new ArtistResource($resolved))->resolve(),
                    'releases' => $page['data'],
                ],
                'links' => $page['links'],
                'meta' => $page['meta'],
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Release>  $releases
     * @return array{data: list<array<string, mixed>>, links: array<string, string|null>, meta: array<string, mixed>}
     */
    private function payload(LengthAwarePaginator $releases): array
    {
        $page = ReleaseResource::collection($releases)->response()->getData(true);

        return [
            'data' => $page['data'],
            'links' => $page['links'],
            'meta' => $page['meta'],
        ];
    }
}
