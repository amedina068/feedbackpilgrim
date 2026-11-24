<?php

use App\Http\Controllers\IframeReleaseNoteController;
use App\Http\Controllers\IframeRoadmapController;
use App\Http\Controllers\IframeTestimonialController;
use Illuminate\Support\Facades\Route;

Route::get('/release-notes', [IframeReleaseNoteController::class, 'index']);
Route::get('/testimonials', [IframeTestimonialController::class, 'index']);
Route::get('/roadmap', [IframeRoadmapController::class, 'index']);
