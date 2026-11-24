<x-public-layout>
  <x-public.breadcrumbs :client="$client">
    <x-public.breadcrumbs.item>Release Notes</x-public.breadcrumbs.item>
  </x-public.breadcrumbs>

  <div class="mt-8">
  <x-public.heading>Release Notes</x-public.heading>

  <ul role="list" class="mt-8 divide-y divide-white/5">
    @forelse($releaseNotes as $releaseNote)
    <x-public.release-note-row :release-note="$releaseNote" :votes="$releaseNote->votes_count" heading="h2" />
    @empty
    <li class="py-16">
      <div class="text-center">
        <h3 class="text-sm font-medium text-fp-hi">No release notes yet</h3>
        <p class="mt-1 text-sm text-fp-low">Updates will show up here once they ship.</p>
      </div>
    </li>
    @endforelse
  </ul>

  <div class="mt-8">
    {{ $releaseNotes->links() }}
  </div>
</x-public-layout>
