<!DOCTYPE html>
<html lang="en" class="scheme-only-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Feedback Pilgrim') }}</title>
    <meta name="description" content="A self-hosted feedback board. Collect feature requests, publish a roadmap and release notes, and sort requests by the revenue of the customers behind them.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&family=jetbrains-mono:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-fp-base">
  <div id="app" class="isolate">
    <div class="min-h-dvh bg-fp-base font-inter text-fp-hi antialiased font-features-['cv02','cv03','cv04','cv11']">

      {{-- Header --}}
      <header class="sticky top-0 z-20 border-b border-fp-line/70 bg-fp-base/80 backdrop-blur-sm">
        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-4 lg:px-8" aria-label="Global">
          <a href="/" aria-label="Homepage" class="-m-1.5 flex items-center gap-2 p-1.5">
            <span class="flex size-7 items-center justify-center rounded-md bg-fp-accent/15 font-mono text-sm font-semibold text-fp-accent">fp</span>
            <span class="font-mono text-sm font-medium tracking-tight text-fp-hi">feedbackpilgrim</span>
          </a>

          <div class="flex items-center gap-x-8 max-lg:hidden">
            <a href="#features" class="text-sm font-medium text-fp-mid hover:text-fp-hi">Features</a>
            <a href="#developers" class="text-sm font-medium text-fp-mid hover:text-fp-hi">Developers</a>
            <a href="#self-host" class="text-sm font-medium text-fp-mid hover:text-fp-hi">Self-host</a>
            <a href="{{ route('documentation') }}" class="text-sm font-medium text-fp-mid hover:text-fp-hi">Docs</a>
          </div>

          <div class="flex items-center gap-x-4">
            <a href="{{ route('login') }}" class="text-sm font-medium text-fp-mid hover:text-fp-hi">Log in</a>
            @if (\App\Support\Registration::isOpen())
              <a href="{{ route('register') }}" class="rounded-md border border-fp-line px-3 py-1.5 text-sm font-medium text-fp-hi hover:bg-fp-surface max-sm:hidden">Create an account</a>
            @endif
          </div>
        </nav>
      </header>

      <main>
        {{-- Hero --}}
        <section class="pt-16 pb-12 sm:pt-24">
          <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="text-center">
              <p class="font-mono text-xs font-medium uppercase tracking-wide text-fp-accent">Open source · Self-hosted</p>
              <h1 class="mx-auto mt-5 max-w-[24ch] text-4xl font-semibold tracking-tight text-balance text-fp-hi sm:text-5xl lg:text-6xl">Feedback Pilgrim</h1>
              <p class="mx-auto mt-6 max-w-[48ch] text-lg text-pretty text-fp-mid">A feedback board that you run on your own server. Sort feature requests by the revenue of the customers who ask for them.</p>
              <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                @if (\App\Support\Registration::isOpen())
                  <a href="{{ route('register') }}" class="w-full rounded-md bg-fp-accent px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-fp-accent-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent sm:w-auto">Create an account</a>
                @else
                  <a href="{{ route('login') }}" class="w-full rounded-md bg-fp-accent px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-fp-accent-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent sm:w-auto">Log in</a>
                @endif
                <a href="{{ route('documentation') }}" class="text-sm font-medium text-fp-mid hover:text-fp-hi">Read the docs &rarr;</a>
              </div>
            </div>

            <div class="mx-auto mt-14 max-w-3xl rounded-xl border border-fp-line bg-fp-surface/60 p-2">
              @include('partials.landing-board')
            </div>
          </div>
        </section>

        {{-- Features --}}
        <section id="features" class="border-y border-fp-line bg-fp-surface/40 py-20">
          <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <p class="font-mono text-xs font-medium uppercase tracking-wide text-fp-accent">Features</p>
            <h2 class="mt-4 max-w-[40ch] text-3xl font-semibold tracking-tight text-balance text-fp-hi">One place for the feedback of each product.</h2>
            <dl class="mt-12 grid grid-cols-1 gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
              <div class="border-l-2 border-fp-accent/40 pl-4">
                <dt class="font-medium text-fp-hi">Feedback boards and votes</dt>
                <dd class="mt-1 text-pretty text-fp-mid">Customers post feature requests, vote and comment on a public site for each project.</dd>
              </div>
              <div class="border-l-2 border-fp-accent/40 pl-4">
                <dt class="font-medium text-fp-hi">Revenue weighting</dt>
                <dd class="mt-1 text-pretty text-fp-mid">Send the MRR and the LTV of each contact. Then sort requests by the revenue behind them.</dd>
              </div>
              <div class="border-l-2 border-fp-accent/40 pl-4">
                <dt class="font-medium text-fp-hi">Roadmap and release notes</dt>
                <dd class="mt-1 text-pretty text-fp-mid">Move posts through stages on a board. Publish release notes and tell the voters when a request ships.</dd>
              </div>
              <div class="border-l-2 border-fp-accent/40 pl-4">
                <dt class="font-medium text-fp-hi">Testimonials</dt>
                <dd class="mt-1 text-pretty text-fp-mid">Ask customers for a rating and a quote. Approve the testimonials before they show.</dd>
              </div>
              <div class="border-l-2 border-fp-accent/40 pl-4">
                <dt class="font-medium text-fp-hi">Embeddable widget</dt>
                <dd class="mt-1 text-pretty text-fp-mid">Add one script tag to your app. It signs in your users and opens the release notes and testimonials.</dd>
              </div>
              <div class="border-l-2 border-fp-accent/40 pl-4">
                <dt class="font-medium text-fp-hi">Webhooks and Slack</dt>
                <dd class="mt-1 text-pretty text-fp-mid">Send signed events to your endpoints, or post a message to a Slack channel.</dd>
              </div>
            </dl>
          </div>
        </section>

        {{-- Developers — MCP + API --}}
        <section id="developers" class="py-20">
          <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <p class="font-mono text-xs font-medium uppercase tracking-wide text-fp-accent">MCP · REST API</p>
            <h2 class="mt-4 max-w-[40ch] text-3xl font-semibold tracking-tight text-balance text-fp-hi">Use it from code or from an AI agent.</h2>
            <p class="mt-6 max-w-[48ch] text-lg text-pretty text-fp-mid">The dashboard actions are also available in a versioned REST API and in an MCP server.</p>

            <div class="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-2">
              <div class="rounded-xl border border-fp-line bg-fp-surface p-6">
                <h3 class="font-mono text-sm font-medium text-fp-hi">Public REST API</h3>
                <p class="mt-2 text-sm/6 text-fp-mid">Sanctum token authentication and one OpenAPI document.</p>
                <ul role="list" class="mt-5 space-y-1.5 font-mono text-xs text-fp-mid">
                  <li><span class="text-fp-success">GET</span> <span class="text-fp-low">/v1/</span>posts</li>
                  <li><span class="text-fp-success">GET</span> <span class="text-fp-low">/v1/</span>contacts</li>
                  <li><span class="text-fp-success">GET</span> <span class="text-fp-low">/v1/</span>release-notes</li>
                  <li><span class="text-fp-accent">POST</span> <span class="text-fp-low">/v1/</span>tags · testimonials</li>
                </ul>
                <div class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium">
                  <a href="{{ route('documentation.reference') }}" class="text-fp-accent hover:text-fp-accent-hover">API reference &rarr;</a>
                  <a href="{{ route('documentation.openapi.download') }}" class="text-fp-mid hover:text-fp-hi">Download OpenAPI &rarr;</a>
                </div>
              </div>

              <div class="rounded-xl border border-fp-line bg-fp-surface p-6">
                <h3 class="font-mono text-sm font-medium text-fp-hi">MCP server</h3>
                <p class="mt-2 text-sm/6 text-fp-mid">Connect Claude or another MCP client. The tools do the same actions as the API, with a bearer token.</p>
                <ul role="list" class="mt-5 space-y-1.5 font-mono text-xs text-fp-mid">
                  <li><span class="text-fp-accent">POST</span> <span class="text-fp-low">/</span>mcp</li>
                  <li><span class="text-fp-low">tools</span> list · get · create · update</li>
                  <li><span class="text-fp-low">on</span> posts · contacts · release-notes</li>
                  <li><span class="text-fp-low">on</span> tags · testimonials</li>
                </ul>
                <div class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium">
                  <a href="{{ route('documentation') }}" class="text-fp-accent hover:text-fp-accent-hover">Set up MCP &rarr;</a>
                </div>
              </div>
            </div>
          </div>
        </section>

        {{-- Self-host --}}
        <section id="self-host" class="border-t border-fp-line bg-fp-surface/40 py-20">
          <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <p class="font-mono text-xs font-medium uppercase tracking-wide text-fp-accent">Self-host</p>
            <h2 class="mt-4 max-w-[40ch] text-3xl font-semibold tracking-tight text-balance text-fp-hi">Your server, your database, your data.</h2>
            <p class="mt-6 max-w-[48ch] text-lg text-pretty text-fp-mid">Feedback Pilgrim is a Laravel application. The repository has a Dockerfile and a Docker Compose file for the web server, the queue worker, the scheduler, MySQL and Redis.</p>
            <div class="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-2">
              <pre class="overflow-x-auto rounded-xl border border-fp-line bg-fp-base p-6 font-mono text-sm/6 text-fp-mid"><code><span class="text-fp-low"># Copy the example environment and set APP_DOMAIN</span>
cp .env.example .env

<span class="text-fp-low"># Build and start the stack</span>
docker compose up -d --build

<span class="text-fp-low"># Create the owner, the first project and its admin contact</span>
docker compose exec web php artisan fp:install</code></pre>
              <dl class="flex flex-col gap-6">
                <div class="border-l-2 border-fp-accent/40 pl-4">
                  <dt class="font-medium text-fp-hi">One subdomain for each project</dt>
                  <dd class="mt-1 text-pretty text-fp-mid">The dashboard runs on <span class="font-mono text-fp-hi">APP_DOMAIN</span>. Each project has a public site on a subdomain, so point a wildcard DNS record at the server.</dd>
                </div>
                <div class="border-l-2 border-fp-accent/40 pl-4">
                  <dt class="font-medium text-fp-hi">Registration is off by default</dt>
                  <dd class="mt-1 text-pretty text-fp-mid">The install command creates the owner account. Set <span class="font-mono text-fp-hi">REGISTRATION_ENABLED=true</span> to let other people sign up.</dd>
                </div>
              </dl>
            </div>
          </div>
        </section>
      </main>

      {{-- Footer --}}
      <footer class="border-t border-fp-line">
        <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 px-6 py-12 sm:flex-row sm:items-center lg:px-8">
          <a href="/" aria-label="Homepage" class="flex items-center gap-2">
            <span class="flex size-7 items-center justify-center rounded-md bg-fp-accent/15 font-mono text-sm font-semibold text-fp-accent">fp</span>
            <span class="font-mono text-sm font-medium tracking-tight text-fp-hi">feedbackpilgrim</span>
          </a>
          <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-fp-mid">
            <a href="{{ route('documentation') }}" class="hover:text-fp-hi">Docs</a>
            <a href="{{ route('login') }}" class="hover:text-fp-hi">Log in</a>
            @if (\App\Support\Registration::isOpen())
              <a href="{{ route('register') }}" class="hover:text-fp-hi">Create an account</a>
            @endif
          </div>
        </div>
      </footer>

    </div>
  </div>
</body>
</html>
