<x-app-layout>

<div>
  <div>
    <x-drawer id="filters" label="Filters" side="right">
      <form action="/contacts" method="GET">
        <div class="py-6">
          <div>
            <label for="sort_by" class="block text-sm font-medium leading-6 text-fp-hi">Sort</label>
            <select id="sort_by" name="sort_by" class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent sm:text-sm sm:leading-6">
              <option @selected((request()->sort_by ?? 'ltv__DESC') == 'ltv__DESC') value="ltv__DESC">Highest LTV</option>
              <option @selected((request()->sort_by ?? 'ltv__ASC') == 'ltv__ASC') value="ltv__ASC">Lowest LTV</option>
              <option @selected((request()->sort_by ?? 'mrr__DESC') == 'mrr__DESC') value="mrr__DESC">Highest MRR</option>
              <option @selected((request()->sort_by ?? 'mrr__ASC') == 'mrr__ASC') value="mrr__ASC">Lowest MRR</option>
              <option @selected((request()->sort_by ?? 'created_at__DESC') == 'created_at__DESC') value="created_at__DESC">Newest</option>
              <option @selected(request()->sort_by == 'created_at__ASC') value="created_at__ASC">Oldest</option>
              <option @selected(request()->sort_by == 'posts_count__DESC') value="posts_count__DESC">Most Posts</option>
              <option @selected(request()->sort_by == 'posts_count__ASC') value="posts_count__ASC">Least Posts</option>
            </select>
          </div>
        </div>
        <div class="border-t border-white/5 py-6">
          <x-filters.group title="Tags">
              @foreach($tags as $tag)
                <div class="flex items-center">
                  <input @checked(in_array($tag->id, request()->get('tags', []))) id="filter-tag-{{ $tag->id }}" name="tags[]" value="{{ $tag->id }}" type="checkbox" class="h-4 w-4 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                  <label for="filter-tag-{{ $tag->id }}" class="ml-3 text-sm text-fp-mid">{{ $tag->title }}</label>
                </div>
              @endforeach
          </x-filters.group>
        </div>
        @foreach($attributes as $attribute)
          <div class="border-t border-white/5 py-6">
            <label for="filter-attribute-{{ $attribute->name }}" class="block text-sm font-medium leading-6 text-fp-hi">{{ ucfirst($attribute->name) }}</label>
            <input type="text" id="filter-attribute-{{ $attribute->name }}" name="attributes[{{ $attribute->name }}]" value="{{ request()->input("attributes.{$attribute->name}", '') }}" placeholder="Filter by {{ $attribute->name }}" class="mt-2 block w-full rounded-md border-0 py-1.5 text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent sm:text-sm sm:leading-6">
          </div>
        @endforeach
        <div class="flex justify-end">
          <button type="submit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Submit</button>
        </div>
      </form>
    </x-drawer>

    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4 py-6">
        <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">Contacts</h1>

        <div class="space-x-4 px-4 sm:px-0 flex items-center">
          <x-filters.button />
        </div>
      </div>

      <section class="md:pt-4 pb-12">
        <div>
            <ul role="list" class="divide-y divide-white/5 overflow-hidden">
              @forelse($contacts as $contact)
                <x-contact-row :contact="$contact" />
              @empty
                <li class="px-4 pt-6 pb-8">
                  <x-empty-state title="No Contacts yet" />
                </li> 
              @endforelse
            </ul>
            <div class="mt-5 px-4 sm:px-0">
                {{ $contacts->withQueryString()->links() }}
            </div>
        </div>
      </section>
    </div>
  </div>
</div>
</x-app-layout>
