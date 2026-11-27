<?php

namespace App\Providers;

use App\Models\Client;
use App\Support\DashboardBanners;
use App\Support\DashboardNavigation;
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

            $extensionItems = $this->extensionNavigationItems();
            $currentExtensionItem = collect($extensionItems)->firstWhere('active', true);

            $view->with([
                'clients' => $clients,
                'client' => $client,
                'contactUrl' => $contactUrl,
                'sidebarItems' => $sidebarItems,
                'extensionItems' => $extensionItems,
                'currentSection' => $currentSection['title'] ?? $currentExtensionItem['label'] ?? $client->name,
            ]);
        });

        View::composer('layouts.app', function (\Illuminate\View\View $view) {
            $view->with('dashboardBanners', app(DashboardBanners::class)->items(Auth::user()));
        });

        View::composer('layouts.public', function (\Illuminate\View\View $view) {
            $client = request()->client;
            $clientDomain = optional($client->settings()->domain()->first())->value;
            View::share('client', $client);
            View::share('clientDomain', $clientDomain);
        });
    }

    /**
     * The links from DashboardNavigation that the current user can see. A
     * link to a path of this app is active when the request is on that path
     * or below it.
     *
     * @return list<array{label: string, url: string, icon: string|null, active: bool}>
     */
    private function extensionNavigationItems(): array
    {
        return array_map(function (array $item): array {
            $path = str_starts_with($item['url'], '/') && ! str_starts_with($item['url'], '//')
                ? trim((string) parse_url($item['url'], PHP_URL_PATH), '/')
                : '';

            return [
                'label' => $item['label'],
                'url' => $item['url'],
                'icon' => $item['icon'],
                'active' => $path !== '' && request()->is($path, "{$path}/*"),
            ];
        }, app(DashboardNavigation::class)->items(Auth::user()));
    }
}
