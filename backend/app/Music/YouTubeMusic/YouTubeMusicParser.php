<?php

namespace App\Music\YouTubeMusic;

use App\Enums\ReleaseType;
use App\Music\Data\ArtistData;
use App\Music\Data\ArtistPageData;
use App\Music\Data\ArtistSectionData;
use App\Music\Data\ReleaseData;
use App\Music\Exceptions\MusicProviderResponseException;

final class YouTubeMusicParser
{
    private const SECTION_TITLES = [
        'albums' => ['albums', 'álbuns', 'álbums'],
        'singles' => ['singles & eps', 'singles e eps'],
    ];

    private const NON_RELEASE_SECTION_TITLES = ['videos', 'featured on', 'live performances', 'apresentações ao vivo', 'em destaque'];

    public function searchArtists(array $data): array
    {
        $sectionLists = $this->findRenderers($data, 'sectionListRenderer');
        if ($sectionLists === []) {
            throw new MusicProviderResponseException('YouTube Music search layout is not recognized.');
        }
        foreach ($sectionLists as $sectionList) {
            if (! is_array($sectionList['contents'] ?? null)) {
                throw new MusicProviderResponseException('YouTube Music search layout is not recognized.');
            }
        }

        $artists = [];

        foreach ($this->findRenderers($data, 'musicResponsiveListItemRenderer') as $renderer) {
            $browseId = data_get($renderer, 'navigationEndpoint.browseEndpoint.browseId');
            $name = $this->firstText($renderer['flexColumns'] ?? []);

            if (! is_string($browseId) || $browseId === '' || $name === null) {
                throw new MusicProviderResponseException('YouTube Music returned an invalid artist result.');
            }

            $artists[$browseId] = new ArtistData($browseId, $name, $this->thumbnail($renderer));
        }

        return array_values($artists);
    }

    public function artistPage(array $data, string $externalId): ArtistPageData
    {
        $tabs = data_get($data, 'contents.singleColumnBrowseResultsRenderer.tabs')
            ?? data_get($data, 'contents.twoColumnBrowseResultsRenderer.tabs');
        $sectionList = is_array($tabs) ? data_get($tabs, '0.tabRenderer.content.sectionListRenderer') : null;
        $artistRenderer = data_get($data, 'header.musicImmersiveHeaderRenderer');

        if (! is_array($sectionList) || ! is_array($sectionList['contents'] ?? null) || ! is_array($artistRenderer)) {
            throw new MusicProviderResponseException('YouTube Music artist page layout is not recognized.');
        }

        $artistName = $this->firstText($artistRenderer['title'] ?? []);
        $artistName ??= $artistRenderer['title']['simpleText'] ?? null;

        if (! is_string($artistName) || $artistName === '') {
            throw new MusicProviderResponseException('YouTube Music artist header is missing.');
        }

        $language = strtolower(substr((string) config('music.youtube_music.hl', 'en'), 0, 2));
        if (! in_array($language, ['en', 'pt'], true)) {
            throw new MusicProviderResponseException('Configured YouTube Music language is not supported for release sections.');
        }

        $sections = [];

        foreach ($sectionList['contents'] as $row) {
            if (! is_array($row) || ! is_array($shelf = $row['musicCarouselShelfRenderer'] ?? null)) {
                continue;
            }

            $header = $shelf['header']['musicCarouselShelfBasicHeaderRenderer'] ?? null;
            $title = is_array($header) ? $this->firstText($header['title'] ?? []) : null;
            if ($title === null) {
                throw new MusicProviderResponseException('YouTube Music carousel is missing its title.');
            }

            $category = $this->categoryForTitle($title);
            if ($category === null) {
                if ($this->isKnownNonReleaseSection($title)) {
                    continue;
                }

                throw new MusicProviderResponseException('YouTube Music returned an unknown carousel category.');
            }
            if ($category === 'albums' || $category === 'singles') {
                $endpoint = $this->sectionEndpoint($header);
                $sections[] = new ArtistSectionData(
                    $title,
                    $endpoint['browseId'] ?? '',
                    $endpoint['params'] ?? null,
                    $this->releaseCards($shelf),
                );
            }
        }

        return new ArtistPageData(
            new ArtistData($externalId, $artistName, $this->thumbnail($artistRenderer)),
            $sections,
        );
    }

    public function releaseCards(array $data): array
    {
        $isCarouselRenderer = is_array(data_get($data, 'header.musicCarouselShelfBasicHeaderRenderer'));
        $items = $isCarouselRenderer && is_array($data['contents'] ?? null) && array_is_list($data['contents'])
            ? $data['contents']
            : (data_get($data, 'contents.singleColumnBrowseResultsRenderer.tabs.0.tabRenderer.content.sectionListRenderer.contents.0.gridRenderer.items')
                ?? data_get($data, 'contents.twoColumnBrowseResultsRenderer.tabs.0.tabRenderer.content.sectionListRenderer.contents.0.gridRenderer.items')
                ?? data_get($data, 'contents.singleColumnBrowseResultsRenderer.tabs.0.tabRenderer.content.sectionListRenderer.contents.0.musicCarouselShelfRenderer.contents')
                ?? data_get($data, 'contents.twoColumnBrowseResultsRenderer.tabs.0.tabRenderer.content.sectionListRenderer.contents.0.musicCarouselShelfRenderer.contents')
                ?? data_get($data, 'continuationContents.gridContinuation.items')
                ?? data_get($data, 'gridRenderer.items'));

        if (! is_array($items) && isset($data['contents']['sectionListRenderer']['contents'])) {
            $section = $data['contents']['sectionListRenderer']['contents'][0] ?? [];
            $items = $section['gridRenderer']['items'] ?? $section['musicCarouselShelfRenderer']['contents'] ?? null;
        }

        if (! is_array($items)) {
            throw new MusicProviderResponseException('YouTube Music release cards are missing.');
        }

        $releases = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new MusicProviderResponseException('YouTube Music returned an invalid release card.');
            }

            $renderer = $item['musicTwoRowItemRenderer'] ?? null;
            if (! is_array($renderer)) {
                throw new MusicProviderResponseException('YouTube Music returned an unknown release card renderer.');
            }

            $browseId = data_get($renderer, 'navigationEndpoint.browseEndpoint.browseId')
                ?? data_get($renderer, 'title.runs.0.navigationEndpoint.browseEndpoint.browseId');
            $title = $this->firstText($renderer['title'] ?? []);

            if (! is_string($browseId) || $browseId === '' || $title === null) {
                throw new MusicProviderResponseException('YouTube Music returned an invalid release card.');
            }

            $releases[$browseId] = $this->releaseFromFields($browseId, $title, $this->releaseFields($renderer), $this->thumbnail($renderer));
        }

        return array_values($releases);
    }

    public function release(array $data, string $externalId): ReleaseData
    {
        $header = data_get($data, 'header.musicResponsiveHeaderRenderer')
            ?? ($this->findRenderers($data, 'musicResponsiveHeaderRenderer')[0] ?? null)
            ?? data_get($data, 'header.musicDetailHeaderRenderer')
            ?? ($this->findRenderers($data, 'musicDetailHeaderRenderer')[0] ?? null);

        if (! is_array($header)) {
            throw new MusicProviderResponseException('YouTube Music release header layout is not recognized.');
        }

        $title = $this->firstText($header['title'] ?? []);
        $title ??= $header['title']['simpleText'] ?? null;

        if (! is_string($title) || $title === '') {
            throw new MusicProviderResponseException('YouTube Music release title is missing.');
        }

        $artists = [];
        $artistRuns = data_get($header, 'straplineTextOne.runs');
        if (! is_array($artistRuns)) {
            $artistRuns = data_get($header, 'subtitle.runs', []);
        }

        foreach ($artistRuns as $run) {
            if (! is_array($run)) {
                continue;
            }

            $artistId = data_get($run, 'navigationEndpoint.browseEndpoint.browseId');
            $name = $run['text'] ?? null;
            if (! $this->isArtistId($artistId) || ! is_string($name) || trim($name) === '') {
                continue;
            }

            $artists[$artistId] = new ArtistData($artistId, trim($name));
        }

        $canonicalUrl = data_get($data, 'microformat.microformatDataRenderer.urlCanonical');
        $sourceUrl = $this->isSafeSourceUrl($canonicalUrl) ? $canonicalUrl : null;

        return $this->releaseFromFields(
            $externalId,
            $title,
            $this->releaseFields($header, ! array_key_exists('straplineTextOne', $header)),
            $this->thumbnail($header),
            $sourceUrl,
            array_values($artists),
        );
    }

    private function releaseFromFields(string $externalId, string $title, array $fields, ?string $thumbnail, ?string $sourceUrl = null, array $artists = []): ReleaseData
    {
        $typeCandidates = [];
        foreach ($fields['typeTexts'] as $text) {
            $candidate = $this->releaseType($text);
            if ($candidate !== null) {
                $typeCandidates[$candidate->value] = trim($text);
            }
        }
        $type = count($typeCandidates) === 1 ? ReleaseType::from(array_key_first($typeCandidates)) : ReleaseType::Unknown;
        $sourceType = $type === ReleaseType::Unknown ? null : reset($typeCandidates);
        $year = null;
        $date = null;
        foreach ($fields['dateTexts'] as $text) {
            $text = trim($text);
            if (preg_match('/^\d{4}$/', $text) === 1) {
                $year = (int) $text;
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $matches) === 1) {
                if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
                    throw new MusicProviderResponseException('YouTube Music returned an invalid release date.');
                }
                $date = $text;
                $year = (int) $matches[1];
            }
        }

        return new ReleaseData($externalId, $title, $type, $sourceType, $year, $date, $thumbnail, $sourceUrl, $artists);
    }

    /** @return array{typeTexts: list<string>, dateTexts: list<string>} */
    private function releaseFields(array $renderer, bool $legacyHeader = false): array
    {
        $subtitleRuns = (array) data_get($renderer, 'subtitle.runs', []);
        $typeTexts = [];
        $dateTexts = [];
        $firstSubtitleText = $subtitleRuns[0]['text'] ?? null;
        $firstSubtitleId = data_get($subtitleRuns, '0.navigationEndpoint.browseEndpoint.browseId');
        if (is_string($firstSubtitleText) && $firstSubtitleId === null) {
            $typeTexts[] = trim($firstSubtitleText);
            if (preg_match('/^\d{4}(?:-\d{2}-\d{2})?$/', trim($firstSubtitleText)) === 1) {
                $dateTexts[] = trim($firstSubtitleText);
            }
        }
        foreach (array_slice($subtitleRuns, $legacyHeader ? 4 : 2) as $run) {
            if (is_string($run['text'] ?? null) && data_get($run, 'navigationEndpoint.browseEndpoint.browseId') === null) {
                $dateTexts[] = trim($run['text']);
            }
        }
        foreach (['subtitle2', 'secondSubtitle'] as $key) {
            $runs = (array) data_get($renderer, "{$key}.runs", []);
            $text = data_get($renderer, "{$key}.simpleText");
            if (is_string($text)) {
                $dateTexts[] = trim($text);
            }
            if (is_string($runs[0]['text'] ?? null) && data_get($runs, '0.navigationEndpoint.browseEndpoint.browseId') === null) {
                $typeTexts[] = trim($runs[0]['text']);
            }
            foreach (array_slice($runs, 1) as $run) {
                if (is_string($run['text'] ?? null) && data_get($run, 'navigationEndpoint.browseEndpoint.browseId') === null) {
                    $dateTexts[] = trim($run['text']);
                }
            }
        }

        return ['typeTexts' => $typeTexts, 'dateTexts' => $dateTexts];
    }

    private function releaseType(string $text): ?ReleaseType
    {
        return match (mb_strtolower(trim($text))) {
            'album', 'álbum', 'álbum completo' => ReleaseType::Album,
            'ep' => ReleaseType::Ep,
            'single' => ReleaseType::Single,
            default => null,
        };
    }

    private function isKnownNonReleaseSection(string $title): bool
    {
        $title = mb_strtolower(trim($title));

        return in_array($title, self::NON_RELEASE_SECTION_TITLES, true)
            || str_starts_with($title, 'playlists by ')
            || str_starts_with($title, 'listas de reprodução de ')
            || str_starts_with($title, 'listas de reprodução por ')
            || in_array($title, ['fans might also like', 'fãs também gostam', 'os fãs também gostam'], true);
    }

    private function categoryForTitle(string $title): ?string
    {
        $title = mb_strtolower(trim($title));
        foreach (self::SECTION_TITLES as $category => $titles) {
            if (in_array($title, $titles, true)) {
                return $category;
            }
        }

        return null;
    }

    private function sectionEndpoint(array $header): ?array
    {
        $endpoint = data_get($header, 'title.runs.0.navigationEndpoint.browseEndpoint')
            ?? data_get($header, 'moreContentButton.buttonRenderer.navigationEndpoint.browseEndpoint');

        if (! is_array($endpoint) || ! is_string($endpoint['browseId'] ?? null) || $endpoint['browseId'] === '') {
            return null;
        }

        return $endpoint;
    }

    private function isArtistId(mixed $id): bool
    {
        return is_string($id) && (str_starts_with($id, 'UC') || str_starts_with($id, 'MPLA'));
    }

    private function isSafeSourceUrl(mixed $url): bool
    {
        if (! is_string($url)) {
            return false;
        }
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && in_array($parts['host'] ?? null, ['music.youtube.com', 'www.youtube.com', 'youtube.com'], true)
            && array_intersect_key($parts, array_flip(['user', 'pass', 'port', 'fragment'])) === [];
    }

    private function thumbnail(array $renderer): ?string
    {
        $thumbnails = data_get($renderer, 'thumbnail.musicThumbnailRenderer.thumbnail.thumbnails')
            ?? data_get($renderer, 'thumbnailRenderer.musicThumbnailRenderer.thumbnail.thumbnails')
            ?? data_get($renderer, 'thumbnail.thumbnails')
            ?? data_get($renderer, 'thumbnailRenderer.thumbnail.thumbnails');

        if (! is_array($thumbnails) || $thumbnails === []) {
            return null;
        }

        usort($thumbnails, fn (array $left, array $right) => (($right['width'] ?? 0) * ($right['height'] ?? 0)) <=> (($left['width'] ?? 0) * ($left['height'] ?? 0)));

        return is_string($thumbnails[0]['url'] ?? null) ? $thumbnails[0]['url'] : null;
    }

    private function firstText(array $data): ?string
    {
        foreach ($this->findRuns($data) as $run) {
            if (is_string($run['text'] ?? null) && trim($run['text']) !== '') {
                return trim($run['text']);
            }
        }

        return is_string($data['simpleText'] ?? null) ? trim($data['simpleText']) : null;
    }

    private function findRuns(array $data): array
    {
        $runs = [];
        $walk = function (array $node) use (&$walk, &$runs): void {
            foreach ($node as $key => $value) {
                if ($key === 'runs' && is_array($value)) {
                    foreach ($value as $run) {
                        if (is_array($run)) {
                            $runs[] = $run;
                        }
                    }
                } elseif (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($data);

        return $runs;
    }

    private function findRenderers(array $data, string $rendererName): array
    {
        $renderers = [];
        $walk = function (array $node) use (&$walk, &$renderers, $rendererName): void {
            foreach ($node as $key => $value) {
                if ($key === $rendererName && is_array($value)) {
                    $renderers[] = $value;
                }
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($data);

        return $renderers;
    }
}
