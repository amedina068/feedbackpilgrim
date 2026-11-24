<?php

use App\Http\Controllers\Api\InitController;
use App\Http\Controllers\Api\ReleaseNoteController;
use App\Http\Controllers\Api\TestimonialController;
use App\Http\Controllers\Api\TestimonialSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('widget_client')->group(function () {
    Route::post('init', [InitController::class, 'index']);
});

Route::middleware(['identifyClient'])->group(function () {
    Route::get('release-notes', [ReleaseNoteController::class, 'index']);
    Route::get('testimonials/settings', [TestimonialSettingController::class, 'index']);
    Route::post('testimonials', [TestimonialController::class, 'store'])->middleware('throttle:5,1');
});
