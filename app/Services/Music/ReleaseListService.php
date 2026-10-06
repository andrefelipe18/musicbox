<?php

namespace App\Services\Music;

use App\Models\Release;
use App\Models\User;
use App\Models\UserRelease;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Builds the filtered, sorted and paginated release listings served by the catalog endpoints.
 */
class ReleaseListService
{
    /**
     * @param array{artist_id?: string, type?: string, status?: string, rating?: int, sort?: string, direction?: string, per_page?: int} $filters
     */
    public function releases(array $filters, ?User $user): LengthAwarePaginator
    {
        $query = Release::query()->with('artists');

        if (isset($filters['artist_id'])) {
            $query->whereHas('artists', fn ($artists) => $artists->where('artists.id', $filters['artist_id']));
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status']) || isset($filters['rating'])) {
            $query->whereHas('userReleases', function ($personal) use ($filters, $user): void {
                $personal->where('user_id', $user?->id);
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
                ->where('user_id', $user?->id)
                ->limit(1), $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query->orderBy('id')
            ->paginate($filters['per_page'] ?? 20)
            ->appends(collect($filters)->except('page')->all());
    }

    /**
     * @param array{artist_id?: string, type?: string, status?: string, rating?: int, sort?: string, direction?: string, per_page?: int} $filters
     */
    public function userReleases(User $user, array $filters): LengthAwarePaginator
    {
        $query = $user->userReleases()->with(['release.artists']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['rating'])) {
            $query->where('rating', $filters['rating']);
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

        return $query->orderBy('user_releases.id')
            ->paginate($filters['per_page'] ?? 20)
            ->appends(collect($filters)->except('page')->all());
    }
}