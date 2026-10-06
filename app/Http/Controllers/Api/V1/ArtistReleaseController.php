<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArtistReleasesRequest;
use App\Models\Artist;
use App\Services\Music\ArtistCatalogService;
use App\Services\Music\ArtistReleasePageService;
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
        $catalog = app(ArtistCatalogService::class);
        $response = app(ArtistReleasePageService::class)->build($artist, $catalog->releases($artist), $request->validated());

        return response()->json($response['body'], $response['status']);
    }
}
