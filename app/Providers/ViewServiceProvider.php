<?php

namespace App\Providers;

use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('layouts.navigation', function (\Illuminate\View\View $view) {
            $sidebarItems = [
                [
                    'title' => 'Dashboard',
                    'path' => 'dashboard',
                    'icon' => 'home-icon',
                ],
                [
                    'title' => 'Posts',
                    'path' => 'posts',
                    'icon' => 'post-icon',
                ],
                [
                    'title' => 'Boards',
                    'path' => 'boards',
                    'icon' => 'board-icon',
                ],
                [
                    'title' => 'Contacts',
                    'path' => 'contacts',
                    'icon' => 'contact-icon',
                ],
                [
                    'title' => 'Tags',
                    'path' => 'tags',
                    'icon' => 'tag-icon',
                ],
                [
                    'title' => 'Release Notes',
                    'path' => 'release-notes',
                    'icon' => 'release-notes-icon',
                ],
                [
                    'title' => 'Testimonials',
                    'path' => 'testimonials',
                    'icon' => 'testimonial-icon',
                ],
            ];

            $client = Client::query()->find(request()->attributes->get('fp_client_id'));
            $clients = Auth::user()->clients()->orderBy('name')->get();

            $contactUrl = Auth::user()->contacts()->where('client_id', $client->id)->first()->generateAuthUrl();

            $sections = array_merge($sidebarItems, [
                ['title' => 'Boards', 'path' => 'board'],
                ['title' => 'Documentation', 'path' => 'documentation'],
                ['title' => 'Settings', 'path' => 'settings'],
                ['title' => 'Profile', 'path' => 'profile'],
                ['title' => 'Admin', 'path' => 'admin'],
            ]);

            $currentSection = collect($sections)
                ->first(fn (array $section) => request()->is("{$section['path']}*"));

            $view->with([
                'clients' => $clients,
                'client' => $client,
                'contactUrl' => $contactUrl,
                'sidebarItems' => $sidebarItems,
                'currentSection' => $currentSection['title'] ?? $client->name,
            ]);
        });

        View::composer('layouts.public', function (\Illuminate\View\View $view) {
            $client = request()->client;
            $clientDomain = optional($client->settings()->domain()->first())->value;
            View::share('client', $client);
            View::share('clientDomain', $clientDomain);
        });
    }
}
