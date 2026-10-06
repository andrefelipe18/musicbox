<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArtistResource;
use App\Models\Artist;

class ArtistController extends Controller
{
    public function show(Artist $artist): ArtistResource
    {
        return new ArtistResource($artist);
    }
}
