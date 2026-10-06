<?php

namespace App\Models;

use App\Enums\UserReleaseStatus;
use Database\Factories\UserReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $release_id
 * @property UserReleaseStatus $status
 * @property int|null $rating
 * @property Carbon|null $listened_at
 * @property string|null $notes
 */
#[Fillable(['release_id', 'status', 'rating', 'listened_at', 'notes'])]
class UserRelease extends Model
{
    /** @use HasFactory<UserReleaseFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => UserReleaseStatus::class,
            'rating' => 'integer',
            'listened_at' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }
}
