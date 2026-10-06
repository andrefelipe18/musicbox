<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReleaseResource;
use App\Models\Release;
use Dedoc\Scramble\Attributes\Group;

#[Group('Release')]
class ShowReleaseController extends Controller
{
    public function __invoke(Release $release): ReleaseResource
    {
        return new ReleaseResource($release->load('artists'));
    }
}
