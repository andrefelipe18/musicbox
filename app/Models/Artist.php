<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $youtube_music_id
 * @property string $name
 * @property string|null $thumbnail_url
 * @property SyncStatus $sync_status
 * @property Carbon|null $catalog_synced_at
 * @property Carbon|null $last_sync_attempt_at
 * @property string|null $last_sync_error
 */
#[Fillable(['youtube_music_id', 'name', 'thumbnail_url', 'catalog_synced_at', 'last_sync_attempt_at', 'sync_status', 'last_sync_error'])]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'sync_status' => SyncStatus::class,
            'catalog_synced_at' => 'datetime',
            'last_sync_attempt_at' => 'datetime',
        ];
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
