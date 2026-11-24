<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * "php artisan route:cache" fails when two routes have the same name. The
 * Docker image caches routes at startup, so every name must be unique.
 */
class RouteNamesTest extends TestCase
{
    public function test_route_names_are_unique(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter();

        $duplicates = $names->duplicates()->unique()->values()->all();

        $this->assertSame([], $duplicates, 'Duplicate route names: '.implode(', ', $duplicates));
    }

    public function test_web_route_names_point_to_the_dashboard(): void
    {
        $this->assertSame('/posts', route('posts.index', absolute: false));
        $this->assertSame('/testimonials/1', route('testimonials.show', 1, absolute: false));
    }
}
