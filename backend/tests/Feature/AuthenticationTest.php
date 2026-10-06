<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\StrictCsrfMiddleware;

it('registers a user and returns session identity without password', function () {
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->postJson('/api/v1/register', [
        'name' => 'Listener',
        'email' => 'listener@example.test',
        'password' => 'Strong-password-123',
        'password_confirmation' => 'Strong-password-123',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Listener')
        ->assertJsonMissingPath('data.password');

    expect(User::where('email', 'listener@example.test')->exists())->toBeTrue();
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'listener@example.test');
});

it('logs in and out using the session guard', function () {
    $this->withHeader('Origin', 'http://localhost:3000');
    $user = User::factory()->create(['password' => Hash::make('Strong-password-123')]);

    $login = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Strong-password-123',
    ])->assertOk()->assertCookie(config('session.cookie'));
    $sessionCookie = $login->getCookie(config('session.cookie'))?->getValue();
    expect($sessionCookie)->not->toBeNull();

    Auth::forgetGuards();
    $this->withCookie(config('session.cookie'), $sessionCookie)
        ->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
    $this->postJson('/api/v1/logout')->assertOk();
    $this->withCookie(config('session.cookie'), $sessionCookie)->getJson('/api/v1/me')->assertUnauthorized();
});

it('requires confirmed passwords and protects the current-user endpoint', function () {
    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/v1/register', [
        'name' => 'Listener',
        'email' => 'listener@example.test',
        'password' => 'Strong-password-123',
        'password_confirmation' => 'not-matching',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('rejects registration and login without a configured stateful origin before writing', function () {
    $payload = [
        'name' => 'No Session',
        'email' => 'no-session@example.test',
        'password' => 'Strong-password-123',
        'password_confirmation' => 'Strong-password-123',
    ];

    $this->postJson('/api/v1/register', $payload)->assertStatus(419);
    $this->withHeader('Origin', 'https://attacker.example')
        ->postJson('/api/v1/register', $payload)
        ->assertStatus(419)
        ->assertExactJson(['message' => 'A trusted stateful session is required.']);
    $this->assertDatabaseMissing('users', ['email' => 'no-session@example.test']);

    $this->postJson('/api/v1/login', ['email' => ['bad'], 'password' => 'x'])
        ->assertStatus(419)
        ->assertExactJson(['message' => 'A trusted stateful session is required.']);
});

it('accepts both configured local origins and initializes session and CSRF cookies', function (string $origin, string $email) {
    $this->withHeader('Origin', $origin);

    $csrf = $this->get('/sanctum/csrf-cookie')->assertNoContent();
    $csrf->assertCookie('XSRF-TOKEN')
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
    $this->postJson('/api/v1/register', [
        'name' => 'Cookie Listener',
        'email' => $email,
        'password' => 'Strong-password-123',
        'password_confirmation' => 'Strong-password-123',
    ])->assertCreated()->assertCookie(config('session.cookie'));

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', $email);
})->with([
    ['http://localhost:3000', 'localhost-session@example.test'],
    ['http://127.0.0.1:3000', 'loopback-session@example.test'],
]);

it('rejects stateful mutations without csrf when csrf middleware is enabled for tests', function () {
    config(['app.debug' => true]);
    config(['sanctum.middleware.validate_csrf_token' => StrictCsrfMiddleware::class]);
    $this->withHeader('Origin', 'http://localhost:3000');

    $this->postJson('/api/v1/register', [
        'name' => 'No CSRF',
        'email' => 'no-csrf@example.test',
        'password' => 'Strong-password-123',
        'password_confirmation' => 'Strong-password-123',
    ])->assertStatus(419)->assertExactJson(['message' => 'CSRF token mismatch.']);

    $this->assertDatabaseMissing('users', ['email' => 'no-csrf@example.test']);
});

it('accepts stateful mutation with matching csrf token and session cookie when csrf is enabled', function () {
    config(['app.debug' => true]);
    config(['sanctum.middleware.validate_csrf_token' => StrictCsrfMiddleware::class]);
    $this->withHeader('Origin', 'http://localhost:3000');

    $csrfResponse = $this->get('/sanctum/csrf-cookie')->assertNoContent();
    $sessionCookie = $csrfResponse->getCookie(config('session.cookie'))?->getValue();
    $csrfToken = $csrfResponse->getCookie('XSRF-TOKEN')?->getValue();
    expect($sessionCookie)->not->toBeNull();
    expect($csrfToken)->not->toBeNull();

    $this->withCookie(config('session.cookie'), $sessionCookie)
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->postJson('/api/v1/register', [
            'name' => 'CSRF Listener',
            'email' => 'csrf-valid@example.test',
            'password' => 'Strong-password-123',
            'password_confirmation' => 'Strong-password-123',
        ])->assertCreated();

    $this->assertDatabaseHas('users', ['email' => 'csrf-valid@example.test']);
});

it('rejects malformed email values with validation instead of limiter errors', function (mixed $email) {
    $this->withHeader('Origin', 'http://localhost:3000')
        ->postJson('/api/v1/login', ['email' => $email, 'password' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
})->with([
    'array' => [['nested']],
    'object' => [(object) ['nested' => true]],
]);

it('uses stable JSON errors in debug mode and preserves rate limit retry headers', function () {
    config(['app.debug' => true]);
    Route::get('/api/v1/__test-forbidden', fn () => throw new AuthorizationException('private policy detail'));
    Route::get('/api/v1/__test-failure', fn () => throw new RuntimeException('internal path and stack detail'));
    Route::get('/api/v1/__test-teapot', fn () => throw new HttpException(418, 'private teapot detail', headers: ['X-Error-Info' => 'private']));

    $this->getJson('/api/v1/artists/search?q=Froid')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
    $this->getJson('/api/v1/__test-forbidden')
        ->assertForbidden()
        ->assertExactJson(['message' => 'Forbidden.']);
    $this->getJson('/api/v1/releases/01JH0000000000000000000000')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not found.']);
    $this->getJson('/api/v1/releases?per_page=101')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The given data was invalid.')
        ->assertJsonMissingPath('exception');
    $this->getJson('/api/v1/__test-failure')
        ->assertInternalServerError()
        ->assertExactJson(['message' => 'Server error.']);
    $this->postJson('/api/v1/releases')
        ->assertStatus(405)
        ->assertExactJson(['message' => 'Method not allowed.'])
        ->assertHeader('Allow', 'GET, HEAD')
        ->assertJsonMissingPath('exception');
    $this->getJson('/api/v1/__test-teapot')
        ->assertStatus(418)
        ->assertExactJson(['message' => 'Request failed.'])
        ->assertHeader('X-Error-Info', 'private');

    $this->withHeader('Origin', 'http://localhost:3000');
    foreach (range(1, 8) as $attempt) {
        $this->postJson('/api/v1/login', ['email' => 'limited@example.test', 'password' => 'wrong'])->assertUnauthorized();
    }
    $this->postJson('/api/v1/login', ['email' => 'limited@example.test', 'password' => 'wrong'])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertExactJson(['message' => 'Too many requests.']);
});
