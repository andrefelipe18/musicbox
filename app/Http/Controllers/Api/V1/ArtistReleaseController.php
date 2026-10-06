<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArtistReleasesRequest;
use App\Http\Resources\Api\V1\ArtistResource;
use App\Http\Resources\Api\V1\ReleaseResource;
use App\Models\Artist;
use App\Models\Release;
use App\Services\Music\ArtistCatalogService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Artist')]
class ArtistReleaseController extends Controller
{
    #[ApiResponse(status: 200, type: 'array{data: array{artist: ArtistResource, releases: list<ReleaseResource>}, links: array{first: string|null, last: string|null, prev: string|null, next: string|null}, meta: array{current_page: int, from: int|null, last_page: int, links: list<array{url: string|null, label: string, active: bool}>, path: string, per_page: int, to: int|null, total: int, sync: array{stale: bool, pending: bool}}}')]
    #[ApiResponse(status: 202, type: 'array{data: array{artist: ArtistResource, releases: list<ReleaseResource>}, links: array{first: string|null, last: string|null, prev: string|null, next: string|null}, meta: array{current_page: int, from: int|null, last_page: int, links: list<array{url: string|null, label: string, active: bool}>, path: string, per_page: int, to: int|null, total: int, sync: array{stale: bool, pending: bool}}}')]
    public function __invoke(ArtistReleasesRequest $request, Artist $artist): JsonResponse
    {
        $catalog = app(ArtistCatalogService::class)->releases($artist);
        $status = ($catalog['pending'] ?? false) && ! ($catalog['stale'] ?? false) ? 202 : 200;
        $artist = $catalog['artist'] ?? $artist;
        $filters = $request->validated();
        $releases = Release::query()
            ->whereHas('artists', fn ($artists) => $artists->where('artists.id', $artist->id))
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->with('artists')
            ->orderBy($filters['sort'] ?? 'title', $filters['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 20)
            ->appends(collect($filters)->except('page')->all());
        /** @var array{
         *     data: list<array{
         *         id: string,
         *         title: string,
         *         type: string,
         *         source_type: string|null,
         *         release_year: int|null,
         *         release_date: string|null,
         *         thumbnail_url: string|null,
         *         source_url: string|null,
         *         artists: list<array{id: string, name: string, thumbnail_url: string|null, sync_status: string, catalog_synced_at: string|null}>
         *     }>,
         *     links: array{first: string|null, last: string|null, prev: string|null, next: string|null},
         *     meta: array{current_page: int, from: int|null, last_page: int, links: list<array{url: string|null, label: string, active: bool}>, path: string, per_page: int, to: int|null, total: int, sync?: array{stale: bool, pending: bool}}
         * } $page
         */
        $page = ReleaseResource::collection($releases)->response()->getData(true);
        $page['meta']['sync'] = [
            'stale' => (bool) ($catalog['stale'] ?? false),
            'pending' => (bool) ($catalog['pending'] ?? false),
        ];

        /** @var array{
         *     data: array{
         *         artist: array{id: string, name: string, thumbnail_url: string|null, sync_status: string, catalog_synced_at: string|null},
         *         releases: list<array{id: string, title: string, type: string, source_type: string|null, release_year: int|null, release_date: string|null, thumbnail_url: string|null, source_url: string|null, artists: list<array{id: string, name: string, thumbnail_url: string|null, sync_status: string, catalog_synced_at: string|null}>}>
         *     },
         *     links: array{first: string|null, last: string|null, prev: string|null, next: string|null},
         *     meta: array{current_page: int, from: int|null, last_page: int, links: list<array{url: string|null, label: string, active: bool}>, path: string, per_page: int, to: int|null, total: int, sync: array{stale: bool, pending: bool}}
         * } $responseData
         */
        $responseData = [
            'data' => [
                'artist' => (new ArtistResource($artist))->resolve(),
                'releases' => $page['data'],
            ],
            'links' => $page['links'],
            'meta' => $page['meta'],
        ];

        return response()->json($responseData, $status);
    }
}
