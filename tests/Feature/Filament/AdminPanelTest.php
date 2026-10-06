<?php

use App\Filament\AvatarProviders\BlobatarProvider;
use App\Models\Admin;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;

it('exposes the admin panel at /admin without a topbar', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getPath())->toBe('admin')
        ->and($panel->hasTopbar())->toBeFalse()
        ->and($panel->getColors())->toBe(['primary' => Color::Purple]);
});

it('resolves user avatars from blobatar over http', function (): void {
    $admin = Admin::factory()->create();

    $url = (new BlobatarProvider)->get($admin);

    expect($url)->toStartWith('https://blobatar.dev/avatar/')
        ->and($url)->toContain(md5((string) $admin->getAuthIdentifier()));
});

it('allows admins but not users to access the admin panel', function (): void {
    $user = User::factory()->create();
    $admin = Admin::factory()->create();

    $this->actingAs($user, 'admin')->get('/admin')->assertForbidden();
    $this->actingAs($admin, 'admin')->get('/admin')->assertOk();
});
