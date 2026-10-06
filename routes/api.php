<?php

use App\Http\Controllers\Api\V1\ArtistController;
use App\Http\Controllers\Api\V1\ArtistReleaseController;
use App\Http\Controllers\Api\V1\ArtistSearchController;
use App\Http\Controllers\Api\V1\ArtistSyncController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ReleaseController;
use App\Http\Controllers\Api\V1\UserReleaseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware(['stateful-session', 'throttle:auth'])->name('api.v1.register');
    Route::post('/login', [AuthController::class, 'login'])->middleware(['stateful-session', 'throttle:auth'])->name('api.v1.login');

    Route::get('/artists/search', ArtistSearchController::class)->middleware(['auth:sanctum', 'throttle:music-search'])->name('api.v1.artists.search');
    Route::get('/artists/{artist}', [ArtistController::class, 'show'])->whereUlid('artist')->name('api.v1.artists.show');
    Route::get('/artists/{artist}/releases', ArtistReleaseController::class)->whereUlid('artist')->middleware(['auth:sanctum', 'throttle:music-search'])->name('api.v1.artists.releases');
    Route::post('/artists/{artist}/sync', ArtistSyncController::class)->whereUlid('artist')->middleware(['auth:sanctum', 'throttle:music-sync'])->name('api.v1.artists.sync');

    Route::get('/releases', [ReleaseController::class, 'index'])->name('api.v1.releases.index');
    Route::get('/releases/{release}', [ReleaseController::class, 'show'])->whereUlid('release')->name('api.v1.releases.show');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.v1.me');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('stateful-session')->name('api.v1.logout');
        Route::get('/me/releases', [UserReleaseController::class, 'index'])->name('api.v1.me.releases.index');
        Route::get('/me/releases/{release}', [UserReleaseController::class, 'show'])->whereUlid('release')->name('api.v1.me.releases.show');
        Route::put('/me/releases/{release}', [UserReleaseController::class, 'update'])->whereUlid('release')->name('api.v1.me.releases.update');
        Route::delete('/me/releases/{release}', [UserReleaseController::class, 'destroy'])->whereUlid('release')->name('api.v1.me.releases.destroy');
    });
});
