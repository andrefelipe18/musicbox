<?php

namespace App\Music\Data;

use App\Enums\ReleaseType;

final readonly class ReleaseData
{
    /** @param list<ArtistData> $artists */
    public function __construct(
        public string $externalId,
        public string $title,
        public ReleaseType $type = ReleaseType::Unknown,
        public ?string $sourceType = null,
        public ?int $releaseYear = null,
        public ?string $releaseDate = null,
        public ?string $thumbnailUrl = null,
        public ?string $sourceUrl = null,
        public array $artists = [],
    ) {}
}
