<?php

namespace App\Music\Contracts;

use App\Music\Data\ArtistData;
use App\Music\Data\ArtistPageData;
use App\Music\Data\ReleaseCollectionData;
use App\Music\Data\ReleaseData;

interface MusicCatalogProvider
{
    /** @return list<ArtistData> */
    public function searchArtists(string $query): array;

    public function getArtist(string $externalId): ArtistPageData;

    public function getArtistReleases(string $externalId): ReleaseCollectionData;

    public function getRelease(string $externalId): ReleaseData;
}
