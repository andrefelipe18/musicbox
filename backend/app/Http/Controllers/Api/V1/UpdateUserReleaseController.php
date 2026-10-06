<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReplaceUserReleaseRequest;
use App\Http\Resources\Api\V1\UserReleaseResource;
use App\Models\Release;
use App\Services\Music\UserCatalogService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('UserRelease')]
class UpdateUserReleaseController extends Controller
{
    public function __invoke(ReplaceUserReleaseRequest $request, Release $release, UserCatalogService $catalog): JsonResponse
    {
        $userRelease = $catalog->replace($this->authenticatedUser($request), $release, $request->validated());

        return (new UserReleaseResource($userRelease->load(['release.artists'])))->response()->setStatusCode(200);
    }
}
