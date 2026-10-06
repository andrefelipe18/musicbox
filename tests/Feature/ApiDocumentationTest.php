<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('publishes every versioned API operation with session-cookie security', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    $this->get('/docs/api')->assertOk();
    $document = $this->getJson('/docs/api.json')->assertOk()->json();
    $this->getJson('/api/v1/artists/search?q=Froid')->assertUnauthorized();

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
    $artistTags = collect([
        ['/artists/search', 'get'],
        ['/artists/{artist}', 'get'],
        ['/artists/{artist}/releases', 'get'],
        ['/artists/{artist}/sync', 'post'],
    ])->map(fn (array $operation): array => $document['paths'][$operation[0]][$operation[1]]['tags'])->unique();

    expect($artistTags->all())->toBe([['Artist']]);

    expect($document['paths']['/artists/{artist}/releases']['get']['responses'])->toHaveKeys(['200', '202'])
        ->and($document['paths']['/artists/{artist}/releases']['get']['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties']['sync']['properties'])
        ->toHaveKeys(['stale', 'pending']);
});

it('serves the docs and the OpenAPI document to admins only', function () {
    $login = route('filament.admin.auth.login');

    foreach (['/docs/api', '/docs/api.json'] as $url) {
        $this->get($url)->assertRedirect($login);

        $this->actingAs(User::factory()->create())->get($url)->assertRedirect($login);
    }

    $this->actingAs(Admin::factory()->create(), 'admin');

    foreach (['/docs/api', '/docs/api.json'] as $url) {
        $this->get($url)->assertOk();
    }
});

it('gates the docs behind the admin session rather than an always-true gate', function () {
    expect(Gate::allows('viewApiDocs'))->toBeFalse();

    $this->actingAs(User::factory()->create());
    expect(Gate::allows('viewApiDocs'))->toBeFalse();

    $this->actingAs(Admin::factory()->create(), 'admin');
    expect(Gate::allows('viewApiDocs'))->toBeTrue();
});

it('serves the MusicBox console theme and brand lockup on the docs page', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    $html = $this->get('/docs/api')->assertOk()->getContent();

    // The MusicBox logo is what Elements renders in the sidebar header...
    expect($html)->toContain('logo="/musicbox-logo.png"');

    // ...and the published view is what overrides Elements' design tokens.
    expect($html)
        ->toContain('--color-canvas: #08070c')        // Void page canvas
        ->toContain('--color-text: #ece9f5')          // Ice primary text
        ->toContain('--color-primary: #7c3aed')       // violet-600, the only fill
        ->toContain('--font-mono: \'JetBrains Mono\'')
        ->toContain('img[src$="musicbox-logo.png"]');  // brand lockup overrides
});
