<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArtistResource;
use App\Models\Artist;
use Dedoc\Scramble\Attributes\Group;

#[Group('Artist')]
class ShowArtistController extends Controller
{
    public function __invoke(Artist $artist): ArtistResource
    {
        return new ArtistResource($artist);
    }
}
