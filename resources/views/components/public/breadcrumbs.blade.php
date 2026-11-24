<nav aria-label="Breadcrumb" class="pt-6">
  <ol role="list" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-medium">
    <li class="flex">
      <a href="/" class="rounded-sm text-fp-low hover:text-fp-hi focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">{{ $client->name }}</a>
    </li>
    {{ $slot }}
  </ol>
</nav>
