<?php

namespace App\Services\Music;

use App\Models\Release;
use App\Models\User;
use App\Models\UserRelease;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class UserCatalogService
{
    /** @param array{status: string, rating?: int|null, listened_at?: string|null, notes?: string|null} $attributes */
    public function replace(User $user, Release $release, array $attributes): UserRelease
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return DB::transaction(fn (): UserRelease => $user->userReleases()->updateOrCreate(
                    ['release_id' => $release->id],
                    [
                        'status' => $attributes['status'],
                        'rating' => $attributes['rating'] ?? null,
                        'listened_at' => $attributes['listened_at'] ?? null,
                        'notes' => $attributes['notes'] ?? null,
                    ],
                ));
            } catch (DeadlockException|QueryException $exception) {
                if ($attempt === 5 || ! $this->isConcurrencyFailure($exception)) {
                    throw $exception;
                }

                usleep(20_000 * (2 ** ($attempt - 1)));
            }
        }

        throw new \RuntimeException('Unreachable transaction retry state.');
    }

    private function isConcurrencyFailure(DeadlockException|QueryException $exception): bool
    {
        return (new ConcurrencyErrorDetector)->causedByConcurrencyError($exception);
    }
}
