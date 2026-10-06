<?php

namespace App\Music\YouTubeMusic;

use App\Music\Contracts\MusicCatalogProvider;
use App\Music\Data\ArtistPageData;
use App\Music\Data\ReleaseCollectionData;
use App\Music\Data\ReleaseData;
use App\Music\Exceptions\MusicProviderException;
use App\Music\Exceptions\MusicProviderResponseException;

final class YouTubeMusicProvider implements MusicCatalogProvider
{
    public function __construct(
        private readonly YouTubeMusicClient $client = new YouTubeMusicClient,
        private readonly YouTubeMusicParser $parser = new YouTubeMusicParser,
    ) {}

    public function searchArtists(string $query): array
    {
        return $this->parser->searchArtists($this->client->searchArtists($query));
    }

    public function getArtist(string $externalId): ArtistPageData
    {
        return $this->parser->artistPage($this->client->browse($externalId), $externalId);
    }

    public function getArtistReleases(string $externalId): ReleaseCollectionData
    {
        return $this->client->withinDeadline(function () use ($externalId): ReleaseCollectionData {
            $maxPages = max(0, (int) config('music.youtube_music.max_pages', 100));
            if ($maxPages < 1) {
                throw new MusicProviderException('YouTube Music discography collection exceeded configured page limit.');
            }

            $pages = 1;
            $artist = $this->getArtist($externalId);
            $releases = [];

            foreach ($artist->sections as $section) {
                $this->mergeReleases($releases, $section->releases);

                if ($section->browseId === '') {
                    continue;
                }

                $this->assertCanRequestPage($pages, $maxPages);
                $pages++;
                $page = $this->client->browse($section->browseId, $section->params);
                $this->mergeReleases($releases, $this->parser->releaseCards($page));

                $continuation = $this->continuation($page);
                $seenTokens = [];

                while ($continuation !== null) {
                    if (isset($seenTokens[$continuation])) {
                        throw new MusicProviderException('YouTube Music repeated a continuation token.');
                    }

                    $seenTokens[$continuation] = true;
                    $this->assertCanRequestPage($pages, $maxPages);
                    $pages++;
                    $page = $this->client->browse($section->browseId, $section->params, $continuation);
                    $this->mergeReleases($releases, $this->parser->releaseCards($page));
                    $continuation = $this->continuation($page);
                }
            }

            return new ReleaseCollectionData(array_values($releases));
        });
    }

    public function getRelease(string $externalId): ReleaseData
    {
        $data = $this->client->browse($externalId);
        $release = $this->parser->release($data, $externalId);
        $sourceUrl = $release->sourceUrl;

        if ($sourceUrl !== null) {
            $parts = parse_url($sourceUrl);
            if (($parts['scheme'] ?? null) !== 'https' || ! in_array($parts['host'] ?? null, ['music.youtube.com', 'www.youtube.com', 'youtube.com'], true)) {
                $sourceUrl = null;
            }
        }

        return new ReleaseData(
            $release->externalId,
            $release->title,
            $release->type,
            $release->sourceType,
            $release->releaseYear,
            $release->releaseDate,
            $release->thumbnailUrl,
            $sourceUrl,
            $release->artists,
        );
    }

    private function continuation(array $data): ?string
    {
        $grid = $this->gridRenderer($data);
        if (! array_key_exists('continuations', $grid)) {
            return null;
        }
        $continuations = $grid['continuations'];

        if (! is_array($continuations)) {
            throw new MusicProviderResponseException('YouTube Music continuation tokens are malformed.');
        }
        if ($continuations === []) {
            return null;
        }

        foreach ($continuations as $item) {
            $token = data_get($item, 'nextContinuationData.continuation')
                ?? data_get($item, 'reloadContinuationData.continuation');

            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        throw new MusicProviderResponseException('YouTube Music returned a malformed continuation token.');
    }

    private function gridRenderer(array $data): array
    {
        $grid = data_get($data, 'contents.singleColumnBrowseResultsRenderer.tabs.0.tabRenderer.content.sectionListRenderer.contents.0.gridRenderer')
            ?? data_get($data, 'contents.twoColumnBrowseResultsRenderer.tabs.0.tabRenderer.content.sectionListRenderer.contents.0.gridRenderer')
            ?? data_get($data, 'continuationContents.gridContinuation')
            ?? data_get($data, 'gridRenderer');

        if (is_array($grid) && is_array($grid['items'] ?? null)) {
            return $grid;
        }

        $carousel = data_get($data, 'contents.singleColumnBrowseResultsRenderer.tabs.0.tabRenderer.content.sectionListRenderer.contents.0.musicCarouselShelfRenderer')
            ?? data_get($data, 'contents.twoColumnBrowseResultsRenderer.tabs.0.tabRenderer.content.sectionListRenderer.contents.0.musicCarouselShelfRenderer');
        if (is_array($carousel) && is_array($carousel['contents'] ?? null)) {
            return [
                'items' => $carousel['contents'],
                'continuations' => $carousel['continuations'] ?? [],
            ];
        }

        throw new MusicProviderResponseException('YouTube Music release list layout is not recognized.');
    }

    private function assertCanRequestPage(int $pages, int $maxPages): void
    {
        if ($pages >= $maxPages) {
            throw new MusicProviderException('YouTube Music discography collection exceeded configured page limit.');
        }
    }

    private function mergeReleases(array &$target, array $releases): void
    {
        foreach ($releases as $release) {
            if (! $release instanceof ReleaseData) {
                throw new MusicProviderResponseException('YouTube Music returned an invalid release record.');
            }
            $target[$release->externalId] ??= $release;
        }
    }
}
