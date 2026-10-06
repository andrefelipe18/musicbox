<?php

namespace App\Services\Music;

final readonly class SyncResult
{
    public function __construct(
        public int $inserted,
        public int $updated,
        public int $skipped,
        public bool $deferred = false,
    ) {}
}
