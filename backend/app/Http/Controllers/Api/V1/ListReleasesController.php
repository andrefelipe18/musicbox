<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListReleasesRequest;
use App\Http\Resources\Api\V1\ReleaseResource;
use App\Services\Music\ReleaseListService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Release')]
class ListReleasesController extends Controller
{
    public function __invoke(ListReleasesRequest $request, ReleaseListService $releases): AnonymousResourceCollection
    {
        return ReleaseResource::collection(
            $releases->releases($request->validated(), $this->optionalUser($request)),
        );
    }
}
