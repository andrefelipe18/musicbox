<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListReleasesRequest;
use App\Http\Resources\Api\V1\ReleaseResource;
use App\Models\Release;
use App\Models\UserRelease;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ReleaseController extends Controller
{
    public function index(ListReleasesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Release::query()->with('artists');

        if (isset($filters['artist_id'])) {
            $query->whereHas('artists', fn ($artists) => $artists->where('artists.id', $filters['artist_id']));
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status']) || isset($filters['rating'])) {
            $userId = Auth::id();
            $query->whereHas('userReleases', function ($personal) use ($filters, $userId): void {
                $personal->where('user_id', $userId);
                if (isset($filters['status'])) {
                    $personal->where('status', $filters['status']);
                }
                if (isset($filters['rating'])) {
                    $personal->where('rating', $filters['rating']);
                }
            });
        }

        $sort = $filters['sort'] ?? 'title';
        $direction = $filters['direction'] ?? 'asc';
        if (in_array($sort, ['rating', 'listened_at'], true)) {
            $query->orderBy(UserRelease::query()
                ->select($sort)
                ->whereColumn('user_releases.release_id', 'releases.id')
                ->where('user_id', Auth::id())
                ->limit(1), $direction);
        } else {
            $query->orderBy($sort, $direction);
        }
        $query->orderBy('id');

        return ReleaseResource::collection(
            $query->paginate($filters['per_page'] ?? 20)->appends(collect($filters)->except('page')->all()),
        );
    }

    public function show(Release $release): ReleaseResource
    {
        return new ReleaseResource($release->load('artists'));
    }
}
