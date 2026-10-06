<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\SyncStatus;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Artist */
class ArtistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'thumbnail_url' => $this->thumbnail_url,
            'sync_status' => ($this->sync_status ?? SyncStatus::Idle)->value,
            'catalog_synced_at' => $this->catalog_synced_at?->utc()->toIso8601String(),
        ];
    }
}
