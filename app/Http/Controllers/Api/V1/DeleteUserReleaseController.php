<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Services\Music\UserCatalogService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('UserRelease')]
class DeleteUserReleaseController extends Controller
{
    public function __invoke(Request $request, Release $release, UserCatalogService $catalog): JsonResponse
    {
        $catalog->remove($this->authenticatedUser($request), $release);

        return response()->json(['message' => 'Release removed from personal catalog.']);
    }
}
