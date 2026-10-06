<?php

it('publishes every versioned API operation with session-cookie security and public docs access', function () {
    $this->app->detectEnvironment(fn (): string => 'local');

    $this->get('/docs/api')->assertOk();
    $document = $this->getJson('/docs/api.json')->assertOk()->json();
    $this->getJson('/api/v1/artists/search?q=Froid')->assertUnauthorized();

    $this->app->detectEnvironment(fn (): string => 'production');
    $this->get('/docs/api')->assertOk();
    $productionDocument = $this->getJson('/docs/api.json')->assertOk()->json();

    $expectedOperations = [
        '/artists/search' => ['get' => true],
        '/artists/{artist}' => ['get' => false],
        '/artists/{artist}/releases' => ['get' => true],
        '/artists/{artist}/sync' => ['post' => true],
        '/releases' => ['get' => false],
        '/releases/{release}' => ['get' => false],
        '/register' => ['post' => false],
        '/login' => ['post' => false],
        '/logout' => ['post' => true],
        '/me' => ['get' => true],
        '/me/releases' => ['get' => true],
        '/me/releases/{release}' => ['get' => true, 'put' => true, 'delete' => true],
    ];

    expect(array_keys($document['paths']))->toEqualCanonicalizing(array_keys($expectedOperations));
    expect(array_keys($productionDocument['paths']))->toEqualCanonicalizing(array_keys($expectedOperations));
    $securitySchemes = collect($document['components']['securitySchemes'])
        ->filter(fn (array $scheme): bool => ($scheme['type'] ?? null) === 'apiKey'
            && ($scheme['in'] ?? null) === 'cookie'
            && ($scheme['name'] ?? null) === config('session.cookie'));
    expect($securitySchemes)->toHaveCount(1);
    $sessionScheme = $securitySchemes->keys()->first();
    expect($document['security'])->toContain([$sessionScheme => []]);

    foreach ($expectedOperations as $path => $methods) {
        foreach ($methods as $method => $requiresSession) {
            $operation = $document['paths'][$path][$method];
            if ($requiresSession) {
                expect($operation['security'] ?? null)->toBeNull();

                continue;
            }

            expect($operation['security'] ?? null)->toBe([]);
        }
    }

    $replaceRequest = $document['components']['schemas']['ReplaceUserReleaseRequest'];
    expect($replaceRequest['required'])->toContain('status')
        ->and($replaceRequest['properties']['rating']['type'])->toBe(['integer', 'null'])
        ->and($replaceRequest['properties']['listened_at']['format'])->toBe('date')
        ->and($replaceRequest['properties']['notes']['type'])->toBe(['string', 'null']);
    expect($document['components']['schemas']['ReleaseResource']['properties']['release_date']['type'])->toBe(['string', 'null'])
        ->and($document['components']['schemas']['UserReleaseResource']['properties']['listened_at']['type'])->toBe(['string', 'null']);
    expect($document['paths']['/artists/{artist}/releases']['get']['responses'])->toHaveKeys(['200', '202'])
        ->and($document['paths']['/artists/{artist}/releases']['get']['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties']['sync']['properties'])
        ->toHaveKeys(['stale', 'pending']);

    $this->app->detectEnvironment(fn (): string => 'testing');
});
