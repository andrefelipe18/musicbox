<?php

namespace App\Music\Data;

final readonly class ArtistData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $thumbnailUrl = null,
    ) {}
}
