<?php

namespace App\Music\YouTubeMusic;

use App\Music\Exceptions\MusicProviderException;
use App\Music\Exceptions\MusicProviderResponseException;
use App\Music\Exceptions\MusicProviderUnavailableException;
use App\Music\Support\MusicRequestBudget;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class YouTubeMusicClient
{
    private const ARTIST_SEARCH_PARAMS = 'EgWKAQIgAWoMEA4QChADEAQQCRAF';

    private const ENDPOINTS = ['search', 'browse'];

    private ?int $activeDeadline = null;

    public function __construct(
        private readonly ?Closure $monotonicClock = null,
        private readonly ?Closure $sleeper = null,
    ) {}

    public function withinDeadline(Closure $callback): mixed
    {
        $previousDeadline = $this->activeDeadline;
        $newDeadline = $this->now() + max(1, (int) config('music.youtube_music.max_duration_seconds', 30)) * 1_000_000_000;
        $budgetRemaining = MusicRequestBudget::remainingSeconds();
        if ($budgetRemaining !== null) {
            $newDeadline = min($newDeadline, $this->now() + max(0, (int) ($budgetRemaining * 1_000_000_000)));
        }
        $this->activeDeadline = $previousDeadline === null ? $newDeadline : min($previousDeadline, $newDeadline);

        try {
            $result = $callback();
            if ($this->remainingSeconds($this->activeDeadline) <= 0) {
                throw new MusicProviderUnavailableException('YouTube Music request deadline exceeded.');
            }

            return $result;
        } finally {
            $this->activeDeadline = $previousDeadline;
        }
    }

    public function post(string $endpoint, array $payload, array $query = []): array
    {
        $config = config('music.youtube_music', []);
        $baseUrl = (string) ($config['base_url'] ?? 'https://music.youtube.com/youtubei/v1');
        $parsedUrl = parse_url($baseUrl);

        if (($parsedUrl['scheme'] ?? null) !== 'https'
            || ($parsedUrl['host'] ?? null) !== 'music.youtube.com'
            || ($parsedUrl['path'] ?? null) !== '/youtubei/v1'
            || array_intersect_key($parsedUrl, array_flip(['user', 'pass', 'port', 'query', 'fragment'])) !== []
            || ! in_array($endpoint, self::ENDPOINTS, true)) {
            throw new MusicProviderException('Invalid YouTube Music endpoint configuration.');
        }

        $clientVersion = '1.'.now('UTC')->format('Ymd').'.01.00';
        $payload['context'] = [
            'client' => [
                'clientName' => 'WEB_REMIX',
                'clientVersion' => $clientVersion,
                'hl' => (string) ($config['hl'] ?? 'en'),
                'gl' => (string) ($config['gl'] ?? 'US'),
            ],
        ];
        $attempts = max(1, (int) ($config['retries'] ?? 2) + 1);
        $deadline = $this->activeDeadline ?? ($this->now() + max(1, (int) ($config['max_duration_seconds'] ?? 30)) * 1_000_000_000);
        $budgetRemaining = MusicRequestBudget::remainingSeconds();
        if ($budgetRemaining !== null) {
            $deadline = min($deadline, $this->now() + max(0, (int) ($budgetRemaining * 1_000_000_000)));
        }
        $requestUrl = "{$baseUrl}/{$endpoint}?alt=json";
        if ($query !== []) {
            $requestUrl .= '&'.http_build_query($query);
        }

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $remainingSeconds = $this->remainingSeconds($deadline);
            $requestTimeout = min((float) ($config['timeout'] ?? 10), $remainingSeconds);
            $connectTimeout = min((float) ($config['connect_timeout'] ?? 5), $remainingSeconds);

            if ($remainingSeconds <= 0) {
                throw new MusicProviderUnavailableException('YouTube Music request deadline exceeded.');
            }

            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->withoutRedirecting()
                    ->timeout($requestTimeout)
                    ->connectTimeout($connectTimeout)
                    ->post($requestUrl, $payload);
            } catch (ConnectionException $exception) {
                if ($attempt === $attempts || $this->remainingSeconds($deadline) <= 0) {
                    throw new MusicProviderUnavailableException('YouTube Music connection failed.', previous: $exception);
                }

                $this->pause((int) ($config['retry_delay_ms'] ?? 250), $deadline);

                continue;
            }

            if ($this->remainingSeconds($deadline) <= 0) {
                throw new MusicProviderUnavailableException('YouTube Music request deadline exceeded.');
            }

            if ($response->successful()) {
                $data = $response->json();
                if (! is_array($data)) {
                    throw new MusicProviderResponseException('YouTube Music returned invalid JSON.');
                }
                if ($this->remainingSeconds($deadline) <= 0) {
                    throw new MusicProviderUnavailableException('YouTube Music request deadline exceeded.');
                }

                return $data;
            }

            if (! $response->serverError() && $response->status() !== 429) {
                throw new MusicProviderException('YouTube Music rejected the request.');
            }

            if ($attempt === $attempts) {
                throw new MusicProviderUnavailableException('YouTube Music is temporarily unavailable.');
            }

            $retryAfter = $this->retryAfterSeconds($response);
            $maximumRetryAfter = max(0, (int) ($config['max_retry_after_seconds'] ?? 5));

            if ($retryAfter !== null && $retryAfter > $maximumRetryAfter) {
                throw new MusicProviderUnavailableException('YouTube Music retry delay exceeds configured limit.');
            }

            $this->pause(($retryAfter ?? 0) * 1000 ?: (int) ($config['retry_delay_ms'] ?? 250), $deadline);
        }

        throw new MusicProviderUnavailableException('YouTube Music is temporarily unavailable.');
    }

    public function searchArtists(string $query): array
    {
        return $this->post('search', [
            'query' => $query,
            'params' => self::ARTIST_SEARCH_PARAMS,
        ]);
    }

    public function browse(string $browseId, ?string $params = null, ?string $continuation = null): array
    {
        $payload = ['browseId' => $browseId];

        if ($params !== null) {
            $payload['params'] = $params;
        }

        if ($continuation !== null) {
            return $this->post('browse', $payload, [
                'ctoken' => $continuation,
                'continuation' => $continuation,
            ]);
        }

        return $this->post('browse', $payload);
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $value = $response->header('Retry-After');

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) ceil((float) $value));
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }

    private function pause(int $milliseconds, int $deadline): void
    {
        $remaining = $deadline - $this->now();

        if ($remaining <= 0 || $milliseconds * 1_000_000 >= $remaining) {
            throw new MusicProviderUnavailableException('YouTube Music request deadline exceeded.');
        }

        if ($milliseconds > 0) {
            if ($this->sleeper !== null) {
                ($this->sleeper)($milliseconds);
            } else {
                usleep($milliseconds * 1000);
            }
        }
    }

    private function remainingSeconds(int $deadline): float
    {
        return ($deadline - $this->now()) / 1_000_000_000;
    }

    private function now(): int
    {
        return $this->monotonicClock === null ? hrtime(true) : ($this->monotonicClock)();
    }
}
