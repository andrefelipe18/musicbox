<?php

namespace App\Music\Data;

final readonly class ArtistSectionData
{
    /** @param list<ReleaseData> $releases */
    public function __construct(
        public string $title,
        public string $browseId,
        public ?string $params = null,
        public array $releases = [],
    ) {}
}
