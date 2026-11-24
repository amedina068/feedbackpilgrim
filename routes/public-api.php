<?php

use App\Http\Controllers\Api\V1\Public\ContactController;
use App\Http\Controllers\Api\V1\Public\InitController;
use App\Http\Controllers\Api\V1\Public\PostController;
use App\Http\Controllers\Api\V1\Public\ReleaseNoteController;
use App\Http\Controllers\Api\V1\Public\TagController;
use App\Http\Controllers\Api\V1\Public\TestimonialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
|
| These routes are for public API access using Sanctum tokens.
| All routes require valid API token authentication.
|
*/

Route::middleware(['auth:sanctum', 'api_client'])->prefix('v1')->name('public-api.')->group(function () {
    Route::post('init', [InitController::class, 'index']);

    Route::apiResource('posts', PostController::class);

    Route::apiResource('release-notes', ReleaseNoteController::class);

    Route::apiResource('contacts', ContactController::class)->except(['destroy']);

    Route::apiResource('testimonials', TestimonialController::class);

    Route::apiResource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);
});
