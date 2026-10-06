<?php

namespace App\Models;

use App\Enums\ReleaseType;
use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $youtube_music_id
 * @property string $title
 * @property ReleaseType $type
 * @property string|null $source_type
 * @property int|null $release_year
 * @property Carbon|null $release_date
 * @property string|null $thumbnail_url
 * @property string|null $source_url
 * @property Carbon|null $metadata_synced_at
 */
#[Fillable(['youtube_music_id', 'title', 'type', 'source_type', 'release_year', 'release_date', 'thumbnail_url', 'source_url', 'metadata_synced_at'])]
class Release extends Model
{
    /** @use HasFactory<ReleaseFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'type' => ReleaseType::class,
            'release_year' => 'integer',
            'release_date' => 'date:Y-m-d',
            'metadata_synced_at' => 'datetime',
        ];
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function userReleases(): HasMany
    {
        return $this->hasMany(UserRelease::class);
    }
}
