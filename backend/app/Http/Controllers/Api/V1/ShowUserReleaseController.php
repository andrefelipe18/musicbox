<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserReleaseResource;
use App\Models\Release;
use App\Services\Music\UserCatalogService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('UserRelease')]
class ShowUserReleaseController extends Controller
{
    public function __invoke(Request $request, Release $release, UserCatalogService $catalog): UserReleaseResource
    {
        return new UserReleaseResource($catalog->find($this->authenticatedUser($request), $release));
    }
}
