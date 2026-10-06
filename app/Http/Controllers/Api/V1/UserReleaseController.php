<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListReleasesRequest;
use App\Http\Requests\Api\V1\ReplaceUserReleaseRequest;
use App\Http\Resources\Api\V1\UserReleaseResource;
use App\Models\Release;
use App\Services\Music\UserCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserReleaseController extends Controller
{
    public function index(ListReleasesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = $request->user()->userReleases()->with(['release.artists']);

        foreach (['status', 'rating'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }
        if (isset($filters['artist_id'])) {
            $query->whereHas('release.artists', fn ($artists) => $artists->where('artists.id', $filters['artist_id']));
        }
        if (isset($filters['type'])) {
            $query->whereHas('release', fn ($release) => $release->where('type', $filters['type']));
        }

        $sort = $filters['sort'] ?? 'title';
        $direction = $filters['direction'] ?? 'asc';
        if (in_array($sort, ['title', 'release_year'], true)) {
            $query->join('releases', 'user_releases.release_id', '=', 'releases.id')
                ->select('user_releases.*')
                ->orderBy('releases.'.$sort, $direction);
        } else {
            $query->orderBy('user_releases.'.$sort, $direction);
        }
        $query->orderBy('user_releases.id');

        return UserReleaseResource::collection(
            $query->paginate($filters['per_page'] ?? 20)->appends(collect($filters)->except('page')->all()),
        );
    }

    public function show(Release $release): UserReleaseResource
    {
        $userRelease = request()->user()->userReleases()->with(['release.artists'])
            ->where('release_id', $release->id)->firstOrFail();
        Gate::authorize('view', $userRelease);

        return new UserReleaseResource($userRelease);
    }

    public function update(ReplaceUserReleaseRequest $request, Release $release): JsonResponse
    {
        $userRelease = app(UserCatalogService::class)->replace($request->user(), $release, $request->validated());
        $userRelease->load(['release.artists']);

        return (new UserReleaseResource($userRelease))->response()->setStatusCode(200);
    }

    public function destroy(Release $release): JsonResponse
    {
        $userRelease = request()->user()->userReleases()->where('release_id', $release->id)->firstOrFail();
        Gate::authorize('delete', $userRelease);
        $userRelease->delete();

        return response()->json(['message' => 'Release removed from personal catalog.']);
    }
}
