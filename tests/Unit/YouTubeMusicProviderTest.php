<?php

use App\Enums\ReleaseType;
use App\Music\Contracts\MusicCatalogProvider;
use App\Music\Exceptions\MusicProviderException;
use App\Music\Exceptions\MusicProviderResponseException;
use App\Music\Exceptions\MusicProviderUnavailableException;
use App\Music\YouTubeMusic\YouTubeMusicClient;
use App\Music\YouTubeMusic\YouTubeMusicProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('music.youtube_music', [
        'base_url' => 'https://music.youtube.com/youtubei/v1',
        'hl' => 'en',
        'gl' => 'US',
        'timeout' => 5,
        'connect_timeout' => 2,
        'retries' => 1,
        'retry_delay_ms' => 0,
        'max_retry_after_seconds' => 1,
        'max_pages' => 5,
        'max_duration_seconds' => 10,
    ]);
    Http::preventStrayRequests();
});

function youtubeFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/youtube-music/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
}

test('provider implements shared music catalog contract', function () {
    expect(new YouTubeMusicProvider)->toBeInstanceOf(MusicCatalogProvider::class);
});

test('searches artist candidates without requiring exact name match', function () {
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('search-artists'))]);

    $artists = (new YouTubeMusicProvider)->searchArtists('artist query');

    expect($artists)->toHaveCount(1)
        ->and($artists[0]->externalId)->toBe('UCARTIST')
        ->and($artists[0]->name)->toBe('Different Display Name');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/search?alt=json')
        && data_get($request->data(), 'query') === 'artist query'
        && data_get($request->data(), 'params') === 'EgWKAQIgAWoMEA4QChADEAQQCRAF');
});

test('parses artist page from single-column renderer and release section pointers', function () {
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('upstream-artist-single-column'))]);

    $page = (new YouTubeMusicProvider)->getArtist('UCJwGWV914kBlV4dKRn7AEFA');

    expect($page->artist->externalId)->toBe('UCJwGWV914kBlV4dKRn7AEFA')
        ->and($page->artist->name)->toBe('Hatsune Miku')
        ->and($page->sections)->toHaveCount(2)
        ->and($page->sections[0]->browseId)->toBe('MPADUCR29q3hGlpPRDrR1bk_8XqQ')
        ->and($page->sections[1]->title)->toBe('Singles & EPs');
});

test('parses artist page from two-column renderer', function () {
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('upstream-artist-two-column'))]);

    $page = (new YouTubeMusicProvider)->getArtist('UCTestChannelId');

    expect($page->artist->name)->toBe('Test Artist')
        ->and($page->sections)->toHaveCount(1)
        ->and($page->sections[0]->browseId)->toBe('')
        ->and($page->sections[0]->releases[0]->externalId)->toBe('MPREb_test123');
});

test('reads section browse ID from title-run navigation without more-content button', function () {
    $artist = youtubeFixture('upstream-artist-single-column');
    foreach ($artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] as &$row) {
        if (($row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '') === 'Albums') {
            unset($row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['moreContentButton']);
        }
    }
    unset($row);
    Http::fake(['music.youtube.com/*' => Http::response($artist)]);

    $page = (new YouTubeMusicProvider)->getArtist('UCJwGWV914kBlV4dKRn7AEFA');

    expect($page->sections[0]->browseId)->toBe('MPADUCR29q3hGlpPRDrR1bk_8XqQ');
});

test('collects release cards from all artist section continuations', function () {
    $artist = youtubeFixture('upstream-artist-single-column');
    foreach ($artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] as &$row) {
        if (isset($row['musicCarouselShelfRenderer'])) {
            $title = $row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '';
            if (in_array($title, ['Albums', 'Singles & EPs'], true)) {
                $row['musicCarouselShelfRenderer']['contents'] = [];
            }
        }
    }
    unset($row);
    Http::fake(function ($request) use ($artist) {
        return match (true) {
            data_get($request->data(), 'browseId') === 'UCJwGWV914kBlV4dKRn7AEFA' => Http::response($artist),
            str_contains($request->url(), 'continuation=grid-page-two') => Http::response(youtubeFixture('grid-page-2')),
            data_get($request->data(), 'params') !== null => Http::response(youtubeFixture('grid-page-1')),
            default => Http::response([], 500),
        };
    });

    $collection = (new YouTubeMusicProvider)->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA');

    expect($collection->complete)->toBeTrue()
        ->and(collect($collection->releases)->pluck('externalId')->all())->toBe(['MPREb_KKkciImCChF', 'MPREb_test123']);
    Http::assertSent(fn ($request) => data_get($request->data(), 'browseId') === 'MPADUCR29q3hGlpPRDrR1bk_8XqQ'
        && data_get($request->data(), 'params') !== null
        && ! array_key_exists('continuation', $request->data())
        && str_contains($request->url(), 'ctoken=grid-page-two')
        && str_contains($request->url(), 'continuation=grid-page-two'));
});

test('recognizes a valid empty artist catalog', function () {
    $artist = youtubeFixture('upstream-artist-two-column');
    $artist['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] = [];
    Http::fake(['music.youtube.com/*' => Http::response($artist)]);

    $collection = (new YouTubeMusicProvider)->getArtistReleases('UCTestChannelId');

    expect($collection->complete)->toBeTrue()->and($collection->releases)->toBe([]);
});

test('fails closed on invalid json or an unknown artist layout', function () {
    Http::fakeSequence()->push('{not json')->push('{}');
    $provider = new YouTubeMusicProvider;

    expect(fn () => $provider->getArtist('UCARTIST'))->toThrow(MusicProviderResponseException::class);
    expect(fn () => $provider->getArtist('UCARTIST'))->toThrow(MusicProviderResponseException::class);
});

test('fails closed when continuation token repeats', function () {
    $artist = youtubeFixture('upstream-artist-single-column');
    foreach ($artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] as &$row) {
        if (isset($row['musicCarouselShelfRenderer']) && in_array($row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '', ['Albums', 'Singles & EPs'], true)) {
            $row['musicCarouselShelfRenderer']['contents'] = [];
        }
    }
    unset($row);
    Http::fake(function ($request) use ($artist) {
        return data_get($request->data(), 'browseId') === 'UCJwGWV914kBlV4dKRn7AEFA'
            ? Http::response($artist)
            : Http::response(youtubeFixture('grid-page-repeated-token'));
    });

    expect(fn () => (new YouTubeMusicProvider)->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA'))
        ->toThrow(MusicProviderException::class);
});

test('fails closed when a grid continuation token is malformed', function () {
    $artist = youtubeFixture('upstream-artist-single-column');
    foreach ($artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] as &$row) {
        if (isset($row['musicCarouselShelfRenderer']) && in_array($row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '', ['Albums', 'Singles & EPs'], true)) {
            $row['musicCarouselShelfRenderer']['contents'] = [];
        }
    }
    unset($row);
    $page = youtubeFixture('grid-page-1');
    $page['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['gridRenderer']['continuations'][0]['nextContinuationData']['continuation'] = '';
    Http::fake(function ($request) use ($artist, $page) {
        return data_get($request->data(), 'browseId') === 'UCJwGWV914kBlV4dKRn7AEFA'
            ? Http::response($artist)
            : Http::response($page);
    });

    expect(fn () => (new YouTubeMusicProvider)->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA'))
        ->toThrow(MusicProviderResponseException::class);
});

test('parses collaborative release details without inventing a full release date', function () {
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('upstream-release'))]);

    $release = (new YouTubeMusicProvider)->getRelease('MPREb_Tr9YVfRfip1');

    expect($release->externalId)->toBe('MPREb_Tr9YVfRfip1')
        ->and($release->type)->toBe(ReleaseType::Album)
        ->and($release->releaseYear)->toBe(2017)
        ->and($release->releaseDate)->toBeNull()
        ->and(array_map(fn ($artist) => $artist->externalId, $release->artists))->toBe(['UCnAcxgRZ065f_eXK1o85c1w']);
});

test('fails without retry when retry-after exceeds configured maximum', function () {
    Http::fake(['music.youtube.com/*' => Http::response('', 429, ['Retry-After' => '999'])]);

    expect(fn () => (new YouTubeMusicProvider)->searchArtists('artist query'))
        ->toThrow(MusicProviderUnavailableException::class);
    Http::assertSentCount(1);
});

test('retries transient status responses', function () {
    Http::fakeSequence()->push('', 503)->push(youtubeFixture('search-artists'));

    expect((new YouTubeMusicProvider)->searchArtists('artist query'))->toHaveCount(1);
    Http::assertSentCount(2);
});

test('does not retry permanent client errors', function () {
    Http::fake(['music.youtube.com/*' => Http::response([], 400)]);

    expect(fn () => (new YouTubeMusicProvider)->searchArtists('artist query'))
        ->toThrow(MusicProviderException::class);
    Http::assertSentCount(1);
});

test('retries connection failures only up to configured attempts', function () {
    Http::fake(['music.youtube.com/*' => Http::failedConnection()]);

    expect(fn () => (new YouTubeMusicProvider)->searchArtists('artist query'))
        ->toThrow(MusicProviderUnavailableException::class);
    Http::assertSentCount(2);
});

test('sends current WEB_REMIX client context without requiring authentication', function () {
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('search-artists'))]);

    (new YouTubeMusicProvider)->searchArtists('artist query');

    Http::assertSent(fn ($request) => data_get($request->data(), 'context.client.clientName') === 'WEB_REMIX'
        && preg_match('/^1\.\d{8}\.01\.00$/', data_get($request->data(), 'context.client.clientVersion')) === 1
        && data_get($request->data(), 'context.client.hl') === 'en'
        && data_get($request->data(), 'context.client.gl') === 'US'
        && ! $request->hasHeader('Authorization'));
});

test('reads the real upstream artist header at response root', function () {
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('upstream-artist-single-column'))]);

    $page = (new YouTubeMusicProvider)->getArtist('UCJwGWV914kBlV4dKRn7AEFA');

    expect($page->artist->name)->toBe('Hatsune Miku')
        ->and($page->sections)->toHaveCount(2)
        ->and(array_map(fn ($section) => $section->title, $page->sections))->toBe(['Albums', 'Singles & EPs']);
});

test('uses pointerless upstream release carousel cards without a follow-up browse', function () {
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('upstream-artist-two-column'))]);

    $collection = (new YouTubeMusicProvider)->getArtistReleases('UCTestChannelId');

    expect($collection->complete)->toBeTrue()
        ->and($collection->releases)->toHaveCount(1)
        ->and($collection->releases[0]->externalId)->toBe('MPREb_test123');
    Http::assertSentCount(1);
});

test('follows nested grid continuation in query and retains body browse pointer', function () {
    $artist = youtubeFixture('upstream-artist-single-column');
    foreach ($artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] as &$row) {
        if (isset($row['musicCarouselShelfRenderer'])) {
            $title = $row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '';
            if (in_array($title, ['Albums', 'Singles & EPs'], true)) {
                $row['musicCarouselShelfRenderer']['contents'] = [];
            }
        }
    }
    unset($row);
    Http::fake(function ($request) use ($artist) {
        if (data_get($request->data(), 'browseId') === 'UCJwGWV914kBlV4dKRn7AEFA') {
            return Http::response($artist);
        }

        if (str_contains($request->url(), 'continuation=grid-page-two')) {
            return Http::response(youtubeFixture('grid-page-2'));
        }

        if (data_get($request->data(), 'params') !== null) {
            return Http::response(youtubeFixture('grid-page-1'));
        }

        return Http::response(['contents' => ['singleColumnBrowseResultsRenderer' => ['tabs' => [['tabRenderer' => ['content' => ['sectionListRenderer' => ['contents' => [['gridRenderer' => ['items' => []]]]]]]]]]]]);
    });

    $collection = (new YouTubeMusicProvider)->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA');

    expect(collect($collection->releases)->pluck('externalId')->contains('MPREb_test123'))->toBeTrue();
    Http::assertSent(fn ($request) => data_get($request->data(), 'browseId') === 'MPADUCR29q3hGlpPRDrR1bk_8XqQ'
        && data_get($request->data(), 'params') !== null
        && ! array_key_exists('continuation', $request->data())
        && str_contains($request->url(), 'ctoken=grid-page-two')
        && str_contains($request->url(), 'continuation=grid-page-two'));
});

test('rejects empty or malformed search and release layouts instead of reporting empty results', function () {
    Http::fakeSequence()->push('{}')->push('{}');
    $provider = new YouTubeMusicProvider;

    expect(fn () => $provider->searchArtists('no candidates'))->toThrow(MusicProviderResponseException::class);
    expect(fn () => $provider->getRelease('RELEASE'))->toThrow(MusicProviderResponseException::class);
});

test('rejects malformed release cards instead of discarding them', function () {
    $artist = youtubeFixture('upstream-artist-single-column');
    foreach ($artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] as &$row) {
        if (isset($row['musicCarouselShelfRenderer']) && in_array($row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '', ['Albums', 'Singles & EPs'], true)) {
            $row['musicCarouselShelfRenderer']['contents'] = [];
        }
    }
    unset($row);
    Http::fake(function ($request) use ($artist) {
        return data_get($request->data(), 'browseId') === 'UCJwGWV914kBlV4dKRn7AEFA'
            ? Http::response($artist)
            : Http::response(youtubeFixture('grid-page-invalid-card'));
    });

    expect(fn () => (new YouTubeMusicProvider)->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA'))
        ->toThrow(MusicProviderResponseException::class);
});

test('rejects empty HTTP 200 continuation layout instead of returning partial catalog', function () {
    $artist = youtubeFixture('upstream-artist-single-column');
    foreach ($artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] as &$row) {
        if (isset($row['musicCarouselShelfRenderer']) && in_array($row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '', ['Albums', 'Singles & EPs'], true)) {
            $row['musicCarouselShelfRenderer']['contents'] = [];
        }
    }
    unset($row);
    Http::fake(function ($request) use ($artist) {
        return data_get($request->data(), 'browseId') === 'UCJwGWV914kBlV4dKRn7AEFA'
            ? Http::response($artist)
            : Http::response(['continuationContents' => ['gridContinuation' => []]]);
    });

    expect(fn () => (new YouTubeMusicProvider)->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA'))
        ->toThrow(MusicProviderResponseException::class);
});

test('rejects bare empty contents as an unrecognized initial release-list layout', function () {
    $artist = youtubeFixture('upstream-artist-single-column');
    foreach ($artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] as &$row) {
        if (isset($row['musicCarouselShelfRenderer']) && in_array($row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '', ['Albums', 'Singles & EPs'], true)) {
            $row['musicCarouselShelfRenderer']['contents'] = [];
        }
    }
    unset($row);
    Http::fake(function ($request) use ($artist) {
        return data_get($request->data(), 'browseId') === 'UCJwGWV914kBlV4dKRn7AEFA'
            ? Http::response($artist)
            : Http::response(['contents' => []]);
    });

    expect(fn () => (new YouTubeMusicProvider)->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA'))
        ->toThrow(MusicProviderResponseException::class);
});

test('filters carousel categories with Portuguese labels before following pointers', function () {
    config()->set('music.youtube_music.hl', 'pt');
    $artist = youtubeFixture('upstream-artist-single-column');
    $sectionContents = &$artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'];
    foreach ($sectionContents as &$row) {
        if (! isset($row['musicCarouselShelfRenderer'])) {
            continue;
        }
        $title = $row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '';
        $row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] = match ($title) {
            'Albums' => 'Álbuns',
            'Singles & EPs' => 'Singles e EPs',
            default => $title,
        };
    }
    unset($row);
    Http::fake(['music.youtube.com/*' => Http::response($artist)]);

    $page = (new YouTubeMusicProvider)->getArtist('UCJwGWV914kBlV4dKRn7AEFA');

    expect(array_map(fn ($section) => $section->title, $page->sections))->toBe(['Álbuns', 'Singles e EPs']);
});

test('extracts release credits only from upstream header strapline', function () {
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('upstream-release'))]);

    $release = (new YouTubeMusicProvider)->getRelease('MPREb_Tr9YVfRfip1');

    expect(array_map(fn ($artist) => $artist->externalId, $release->artists))->toBe(['UCnAcxgRZ065f_eXK1o85c1w'])
        ->and($release->type)->toBe(ReleaseType::Album)
        ->and($release->releaseYear)->toBe(2017);
});

test('classifies release types from semantic fields and rejects impossible dates', function () {
    $response = youtubeFixture('upstream-release');
    $header = &$response['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['musicResponsiveHeaderRenderer'];
    $header['title'] = ['runs' => [['text' => 'Single Mothers']]];
    $header['subtitle']['runs'] = [['text' => 'Single Mothers'], ['text' => ' • '], ['text' => '2024']];
    $header['straplineTextOne']['runs'] = [];
    Http::fake(function () use (&$response) {
        return Http::response($response);
    });

    $release = (new YouTubeMusicProvider)->getRelease('MPREb_Tr9YVfRfip1');

    expect($release->type)->toBe(ReleaseType::Unknown)
        ->and($release->releaseYear)->toBe(2024);

    $header['subtitle']['runs'][2]['text'] = '2024-02-31';
    expect(fn () => (new YouTubeMusicProvider)->getRelease('MPREb_Tr9YVfRfip1'))
        ->toThrow(MusicProviderResponseException::class);
});

test('classifies explicit Portuguese release type without scanning artist/title text', function () {
    $response = youtubeFixture('upstream-release');
    $header = &$response['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['musicResponsiveHeaderRenderer'];
    $header['title'] = ['runs' => [['text' => 'Single Mothers']]];
    $header['subtitle']['runs'] = [['text' => 'Álbum'], ['text' => ' • '], ['text' => '2024']];
    Http::fake(['music.youtube.com/*' => Http::response($response)]);

    $release = (new YouTubeMusicProvider)->getRelease('MPREb_Tr9YVfRfip1');

    expect($release->type)->toBe(ReleaseType::Album)
        ->and($release->releaseYear)->toBe(2024);
});

test('fails explicitly when configured artist-section language is unsupported', function () {
    config()->set('music.youtube_music.hl', 'es');
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('upstream-artist-single-column'))]);

    expect(fn () => (new YouTubeMusicProvider)->getArtist('UCJwGWV914kBlV4dKRn7AEFA'))
        ->toThrow(MusicProviderResponseException::class);
});

test('rejects unsafe base URL components and does not follow redirects', function () {
    foreach ([
        'https://user:pass@music.youtube.com/youtubei/v1',
        'https://music.youtube.com:444/youtubei/v1',
        'https://music.youtube.com/youtubei/v1?redirect=1',
        'https://music.youtube.com/youtubei/v1#fragment',
    ] as $baseUrl) {
        config()->set('music.youtube_music.base_url', $baseUrl);
        expect(fn () => (new YouTubeMusicProvider)->searchArtists('query'))
            ->toThrow(MusicProviderException::class);
    }

    config()->set('music.youtube_music.base_url', 'https://music.youtube.com/youtubei/v1');
    Http::fake(['music.youtube.com/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])]);
    expect(fn () => (new YouTubeMusicProvider)->searchArtists('query'))->toThrow(MusicProviderException::class);
    Http::assertSentCount(1);
});

test('applies one fake-clock deadline to artist lookup, page requests, retries and response time', function () {
    $now = 0;
    $clock = function () use (&$now): int {
        return $now;
    };
    $sleep = function (int $milliseconds) use (&$now): void {
        $now += $milliseconds * 1_000_000;
    };
    config()->set('music.youtube_music.max_duration_seconds', 1);
    $client = new YouTubeMusicClient(monotonicClock: $clock, sleeper: $sleep);
    Http::fake(function () use (&$now) {
        $now += 1_000_000_001;

        return Http::response(youtubeFixture('upstream-artist-single-column'));
    });

    expect(fn () => (new YouTubeMusicProvider(client: $client))
        ->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA'))
        ->toThrow(MusicProviderUnavailableException::class);
    Http::assertSentCount(1);
});

test('honors retry-after total deadline without sleeping or issuing another request', function () {
    $now = 0;
    $clock = function () use (&$now): int {
        return $now;
    };
    $sleep = function (int $milliseconds) use (&$now): void {
        $now += $milliseconds * 1_000_000;
    };
    config()->set('music.youtube_music.max_retry_after_seconds', 2);
    config()->set('music.youtube_music.max_duration_seconds', 1);
    Http::fake(['music.youtube.com/*' => Http::response('', 429, ['Retry-After' => '1'])]);
    $client = new YouTubeMusicClient(monotonicClock: $clock, sleeper: $sleep);

    expect(fn () => $client->searchArtists('query'))
        ->toThrow(MusicProviderUnavailableException::class);
    Http::assertSentCount(1);
});

test('checks page limit before issuing next browse request', function () {
    config()->set('music.youtube_music.max_pages', 1);
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('upstream-artist-single-column'))]);

    expect(fn () => (new YouTubeMusicProvider)->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA'))
        ->toThrow(MusicProviderException::class);
    Http::assertSentCount(1);
});

test('discography deadline includes artist lookup and subsequent release page', function () {
    $now = 0;
    $clock = function () use (&$now): int {
        return $now;
    };
    config()->set('music.youtube_music.max_duration_seconds', 1);
    $client = new YouTubeMusicClient(monotonicClock: $clock);
    Http::fake(function ($request) use (&$now) {
        if (data_get($request->data(), 'browseId') === 'UCJwGWV914kBlV4dKRn7AEFA') {
            $now += 600_000_000;

            return Http::response(youtubeFixture('upstream-artist-single-column'));
        }
        $now += 500_000_000;

        return Http::response(youtubeFixture('grid-page-1'));
    });

    expect(fn () => (new YouTubeMusicProvider(client: $client)->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA')))
        ->toThrow(MusicProviderUnavailableException::class);
    Http::assertSentCount(2);
});

test('rejects search section lists without contents', function () {
    Http::fake(['music.youtube.com/*' => Http::response(['contents' => ['tabbedSearchResultsRenderer' => ['tabs' => [['tabRenderer' => ['content' => ['sectionListRenderer' => []]]]]]]])]);

    expect(fn () => (new YouTubeMusicProvider)->searchArtists('no candidates'))
        ->toThrow(MusicProviderResponseException::class);
});

test('rejects missing-title release carousel instead of returning complete empty catalog', function () {
    $artist = youtubeFixture('upstream-artist-single-column');
    foreach ($artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] as &$row) {
        if (($row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '') === 'Albums') {
            unset($row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']);
        }
    }
    unset($row);
    Http::fake(['music.youtube.com/*' => Http::response($artist)]);

    expect(fn () => (new YouTubeMusicProvider)->getArtist('UCJwGWV914kBlV4dKRn7AEFA'))
        ->toThrow(MusicProviderResponseException::class);
});

test('rejects unknown release renderer in a grid', function () {
    $artist = youtubeFixture('upstream-artist-single-column');
    foreach ($artist['contents']['singleColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'] as &$row) {
        if (isset($row['musicCarouselShelfRenderer']) && in_array($row['musicCarouselShelfRenderer']['header']['musicCarouselShelfBasicHeaderRenderer']['title']['runs'][0]['text'] ?? '', ['Albums', 'Singles & EPs'], true)) {
            $row['musicCarouselShelfRenderer']['contents'] = [];
        }
    }
    unset($row);
    Http::fake(function ($request) use ($artist) {
        return data_get($request->data(), 'browseId') === 'UCJwGWV914kBlV4dKRn7AEFA'
            ? Http::response($artist)
            : Http::response(['continuationContents' => ['gridContinuation' => ['items' => [['unknownRenderer' => []]]]]]);
    });

    expect(fn () => (new YouTubeMusicProvider)->getArtistReleases('UCJwGWV914kBlV4dKRn7AEFA'))
        ->toThrow(MusicProviderResponseException::class);
});

test('preserves ordered strapline collaborator credits and legacy subtitle fallback', function () {
    $response = youtubeFixture('upstream-release');
    $header = &$response['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['musicResponsiveHeaderRenderer'];
    $header['straplineTextOne']['runs'][] = ['text' => 'Second Artist', 'navigationEndpoint' => ['browseEndpoint' => ['browseId' => 'UCSecondArtist']]];
    Http::fake(function () use (&$response) {
        return Http::response($response);
    });

    $release = (new YouTubeMusicProvider)->getRelease('MPREb_Tr9YVfRfip1');
    expect(array_map(fn ($artist) => $artist->externalId, $release->artists))
        ->toBe(['UCnAcxgRZ065f_eXK1o85c1w', 'UCSecondArtist']);

    unset($header['straplineTextOne']);
    array_splice($header['subtitle']['runs'], 2, 0, [['text' => 'Legacy Artist', 'navigationEndpoint' => ['browseEndpoint' => ['browseId' => 'UCLegacyArtist']]]]);
    $legacy = (new YouTubeMusicProvider)->getRelease('MPREb_Tr9YVfRfip1');
    expect(array_map(fn ($artist) => $artist->externalId, $legacy->artists))->toBe(['UCLegacyArtist']);
});

test('does not treat numeric collaborator names or other subtitle slots as release metadata', function () {
    $response = youtubeFixture('upstream-release');
    $header = &$response['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['musicResponsiveHeaderRenderer'];
    $header['straplineTextOne']['runs'] = [];
    $header['subtitle']['runs'] = [['text' => 'Album'], ['text' => ' • '], ['text' => '1999'], ['text' => ' • '], ['text' => '2017']];
    $header['subtitle2']['runs'][0]['text'] = 'Single';
    Http::fake(function () use (&$response) {
        return Http::response($response);
    });

    $release = (new YouTubeMusicProvider)->getRelease('MPREb_Tr9YVfRfip1');
    expect($release->artists)->toBe([])
        ->and($release->releaseYear)->toBe(2017)
        ->and($release->type)->toBe(ReleaseType::Unknown)
        ->and($release->sourceType)->toBeNull();
});

test('uses legacy header year slot and ignores artist-like value when date is omitted', function () {
    $response = youtubeFixture('upstream-release');
    $header = &$response['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['musicResponsiveHeaderRenderer'];
    unset($header['straplineTextOne']);
    $header['subtitle']['runs'] = [['text' => 'Album'], ['text' => ' • '], ['text' => '1999']];
    Http::fake(function () use (&$response) {
        return Http::response($response);
    });

    $release = (new YouTubeMusicProvider)->getRelease('MPREb_Tr9YVfRfip1');

    expect($release->releaseYear)->toBeNull();

    $header['subtitle']['runs'] = [['text' => 'Album'], ['text' => ' • '], ['text' => '1999'], ['text' => ' • '], ['text' => '2014']];
    $releaseWithYear = (new YouTubeMusicProvider)->getRelease('MPREb_Tr9YVfRfip1');

    expect($releaseWithYear->releaseYear)->toBe(2014);
});

test('parses release year from list card type separator year slots', function () {
    $artist = youtubeFixture('upstream-artist-two-column');
    $artist['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][1]['musicCarouselShelfRenderer']['contents'][0]['musicTwoRowItemRenderer']['subtitle']['runs'] = [
        ['text' => 'Album'],
        ['text' => ' • '],
        ['text' => '2014'],
    ];
    Http::fake(['music.youtube.com/*' => Http::response($artist)]);

    $collection = (new YouTubeMusicProvider)->getArtistReleases('UCTestChannelId');

    expect($collection->releases[0]->releaseYear)->toBe(2014);
});

test('records disabled redirect transport and request timeouts bounded by remaining deadline', function () {
    $times = [0, 250_000_000];
    $clock = function () use (&$times): int {
        return array_shift($times) ?? 250_000_000;
    };
    $transportOptions = null;
    config()->set('music.youtube_music.max_duration_seconds', 1);
    Http::globalMiddleware(function (callable $handler) use (&$transportOptions): Closure {
        return function ($request, array $options) use ($handler, &$transportOptions) {
            $transportOptions = $options;

            return $handler($request, $options);
        };
    });
    Http::fake(['music.youtube.com/*' => Http::response(youtubeFixture('search-artists'))]);
    $client = new YouTubeMusicClient(monotonicClock: $clock);

    $client->withinDeadline(fn () => $client->searchArtists('artist query'));

    expect($transportOptions['allow_redirects'])->toBeFalse()
        ->and($transportOptions['timeout'])->toBeLessThanOrEqual(0.75)
        ->and($transportOptions['connect_timeout'])->toBeLessThanOrEqual(0.75);
});

test('checks aggregate deadline after collection callback processing finishes', function () {
    $now = 0;
    $clock = function () use (&$now): int {
        return $now;
    };
    config()->set('music.youtube_music.max_duration_seconds', 1);
    $client = new YouTubeMusicClient(monotonicClock: $clock);

    $work = function () use ($client, &$now) {
        return $client->withinDeadline(function () use (&$now) {
            $now = 2_000_000_000;

            return true;
        });
    };

    expect($work)->toThrow(MusicProviderUnavailableException::class);
});

test('does not invoke sleeper when retry-after exactly consumes remaining deadline', function () {
    $now = 0;
    $clock = function () use (&$now): int {
        return $now;
    };
    $sleeps = [];
    $sleep = function (int $milliseconds) use (&$sleeps, &$now): void {
        $sleeps[] = $milliseconds;
        $now += $milliseconds * 1_000_000;
    };
    config()->set('music.youtube_music.max_duration_seconds', 1);
    config()->set('music.youtube_music.max_retry_after_seconds', 2);
    Http::fake(['music.youtube.com/*' => Http::response('', 429, ['Retry-After' => '1'])]);
    $client = new YouTubeMusicClient(monotonicClock: $clock, sleeper: $sleep);

    expect(fn () => $client->searchArtists('artist query'))
        ->toThrow(MusicProviderUnavailableException::class);
    expect($sleeps)->toBe([]);
});
