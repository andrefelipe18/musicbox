<?php

use App\Http\Controllers\Api\V1\ArtistReleaseController;
use App\Http\Controllers\Api\V1\ArtistSearchController;
use App\Http\Controllers\Api\V1\ArtistSyncController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\DeleteUserReleaseController;
use App\Http\Controllers\Api\V1\ListReleasesController;
use App\Http\Controllers\Api\V1\ListUserReleasesController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\ShowArtistController;
use App\Http\Controllers\Api\V1\ShowReleaseController;
use App\Http\Controllers\Api\V1\ShowUserReleaseController;
use App\Http\Controllers\Api\V1\UpdateUserReleaseController;
use Illuminate\Support\Facades\Route;

// Operation grouping in the documentation lives on the controllers (Dedoc\Scramble\Attributes\Group),
// not here: this file only declares the versioned surface and its middleware.
Route::prefix('v1')->group(function (): void {
    Route::post('/register', RegisterController::class)->middleware(['stateful-session', 'throttle:auth'])->name('api.v1.register');
    Route::post('/login', LoginController::class)->middleware(['stateful-session', 'throttle:auth'])->name('api.v1.login');
    Route::post('/logout', LogoutController::class)->middleware(['stateful-session', 'auth:sanctum'])->name('api.v1.logout');
    Route::get('/me', CurrentUserController::class)->middleware('auth:sanctum')->name('api.v1.me');

    Route::get('/artists/search', ArtistSearchController::class)->middleware(['auth:sanctum', 'throttle:music-search'])->name('api.v1.artists.search');
    Route::get('/artists/{artist}', ShowArtistController::class)->whereUlid('artist')->name('api.v1.artists.show');
    Route::get('/artists/{artist}/releases', ArtistReleaseController::class)->whereUlid('artist')->middleware(['auth:sanctum', 'throttle:music-search'])->name('api.v1.artists.releases');
    Route::post('/artists/{artist}/sync', ArtistSyncController::class)->whereUlid('artist')->middleware(['auth:sanctum', 'throttle:music-sync'])->name('api.v1.artists.sync');

    Route::get('/releases', ListReleasesController::class)->name('api.v1.releases.index');
    Route::get('/releases/{release}', ShowReleaseController::class)->whereUlid('release')->name('api.v1.releases.show');

    Route::get('/me/releases', ListUserReleasesController::class)->middleware('auth:sanctum')->name('api.v1.me.releases.index');
    Route::get('/me/releases/{release}', ShowUserReleaseController::class)->whereUlid('release')->middleware('auth:sanctum')->name('api.v1.me.releases.show');
    Route::put('/me/releases/{release}', UpdateUserReleaseController::class)->whereUlid('release')->middleware('auth:sanctum')->name('api.v1.me.releases.update');
    Route::delete('/me/releases/{release}', DeleteUserReleaseController::class)->whereUlid('release')->middleware('auth:sanctum')->name('api.v1.me.releases.destroy');
});
