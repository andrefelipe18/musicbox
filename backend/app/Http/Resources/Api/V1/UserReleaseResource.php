<?php

namespace App\Http\Resources\Api\V1;

use App\Models\UserRelease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserRelease */
class UserReleaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->release_id,
            'status' => $this->status->value,
            'rating' => $this->rating,
            'listened_at' => $this->listened_at?->toDateString(),
            'notes' => $this->notes,
            'release' => $this->whenLoaded('release', fn () => new ReleaseResource($this->release)),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }
}
