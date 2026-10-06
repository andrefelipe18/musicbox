<?php

namespace App\Music\Data;

final readonly class ArtistPageData
{
    /** @param list<ArtistSectionData> $sections */
    public function __construct(
        public ArtistData $artist,
        public array $sections,
    ) {}
}
