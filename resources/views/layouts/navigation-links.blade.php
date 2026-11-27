<nav aria-label="Main" class="flex flex-1 flex-col gap-y-4">
  <client-selector :clients="{{ $clients }}" :client="{{ $client }}"></client-selector>

  <ul role="list" class="flex flex-col gap-y-0.5">
    @foreach ($sidebarItems as $item)
      <li>
        <x-sidebar-link href="/{{ $item['path'] }}" :active="request()->is($item['path'].'*')">
          <x-dynamic-component :component="$item['icon']" class="size-6 shrink-0" aria-hidden="true" />
          {{ $item['title'] }}
        </x-sidebar-link>
      </li>
    @endforeach
  </ul>

  <ul role="list" class="flex flex-col gap-y-0.5 border-t border-white/5 pt-4">
    <li>
      <x-sidebar-link :href="$contactUrl">Public Site</x-sidebar-link>
    </li>
    <li>
      <x-sidebar-link :href="route('documentation')">
        <svg class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
        </svg>
        Documentation
      </x-sidebar-link>
    </li>
    <li>
      <x-sidebar-link href="/settings" :active="request()->is('settings*')">
        <svg class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.781l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>
        Settings
      </x-sidebar-link>
    </li>
    @if (Auth::user()->isOwner())
      <li>
        <x-sidebar-link href="/admin/users" :active="request()->is('admin*')">
          <svg class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
          </svg>
          Admin
        </x-sidebar-link>
      </li>
    @endif
@foreach ($extensionItems as $item) {{-- Links from App\Support\DashboardNavigation. The directives start at column 0, so an empty list adds no bytes. --}}
    <li>
      <x-sidebar-link :href="$item['url']" :active="$item['active']">
@if ($item['icon'] !== null)
        <x-dynamic-component :component="$item['icon']" class="size-6 shrink-0" aria-hidden="true" />
@endif
        {{ $item['label'] }}
      </x-sidebar-link>
    </li>
@endforeach
  </ul>

  <x-sidebar-link href="/profile" :active="request()->is('profile*')" class="mt-auto">
    <svg class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
    </svg>
    <span class="min-w-0 truncate">Your profile</span>
  </x-sidebar-link>
</nav>
