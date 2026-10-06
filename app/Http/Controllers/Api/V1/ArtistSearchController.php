<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchArtistsRequest;
use App\Http\Resources\Api\V1\ArtistResource;
use App\Services\Music\ArtistSearchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArtistSearchController extends Controller
{
    public function __invoke(SearchArtistsRequest $request): AnonymousResourceCollection
    {
        $artists = app(ArtistSearchService::class)->search($request->validated('q'));

        return ArtistResource::collection($artists);
    }
}
