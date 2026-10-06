<?php

namespace App\Music\Data;

final readonly class ReleaseCollectionData
{
    /** @param list<ReleaseData> $releases */
    public function __construct(
        public array $releases,
        public bool $complete = true,
    ) {}
}
