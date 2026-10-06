<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArtistSyncRequest;
use App\Models\Artist;
use App\Services\Music\ArtistCatalogService;
use Illuminate\Http\JsonResponse;

class ArtistSyncController extends Controller
{
    public function __invoke(ArtistSyncRequest $request, Artist $artist): JsonResponse
    {
        app(ArtistCatalogService::class)->queueSync($artist, $request->validated('force') ?? true);

        return response()->json(['message' => 'Catalog synchronization queued.'], 202);
    }
}
