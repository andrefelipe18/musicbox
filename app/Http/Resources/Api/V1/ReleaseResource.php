<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Release */
class ReleaseResource extends JsonResource
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
            'title' => $this->title,
            'type' => $this->type->value,
            'source_type' => $this->source_type,
            'release_year' => $this->release_year,
            'release_date' => $this->release_date?->toDateString(),
            'thumbnail_url' => $this->thumbnail_url,
            'source_url' => $this->source_url,
            'artists' => $this->whenLoaded('artists', fn () => ArtistResource::collection($this->artists)),
        ];
    }
}
