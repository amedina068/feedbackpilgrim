<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-fp-base has-[dialog[open]]:overflow-hidden" style="color-scheme: dark;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="dark">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="manifest" href="/sw-manifest.json">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&family=jetbrains-mono:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/quill@2.0.0-rc.5/dist/quill.snow.css" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-inter antialiased h-full bg-fp-base text-fp-hi font-features-['cv02','cv03','cv04','cv11']">
      <div id="app" class="isolate" v-cloak>
        <div>
          @include('layouts.navigation')

          <main class="py-10 lg:pl-72">
            <div class="px-4 sm:px-6 lg:px-8">
@if ($dashboardBanners !== []) {{-- Banners from App\Support\DashboardBanners. The directives start at column 0, so an empty list adds no bytes. --}}
              <section aria-label="Notices" class="mb-6 flex flex-col gap-y-2">
                @foreach ($dashboardBanners as $banner)
                  <p class="rounded-lg bg-fp-accent/10 px-4 py-3 text-sm text-fp-hi ring-1 ring-fp-accent/20">
                    {{ $banner['message'] }}
                    @if ($banner['url'] !== null)
                      <a href="{{ $banner['url'] }}" class="font-medium text-fp-accent hover:text-fp-accent-hover">{{ $banner['link'] }}</a>
                    @endif
                  </p>
                @endforeach
              </section>
@endif
              <x-flash class="mb-6" />
              {{ $slot }}
            </div>
            {{ $foot ?? '' }}
          </main>
        </div>
      </div>
      <script>
        document.addEventListener("DOMContentLoaded", () => {
          window.hljs.highlightAll()
          document.querySelectorAll('[name=client-selector]')
        })
      </script>
      <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/js/sw.js').catch((error) => {
          console.error('Service Worker registration failed:', error);
          });
        });
      }
    </script>

    </body>
</html>
