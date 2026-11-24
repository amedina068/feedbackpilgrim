<x-app-layout>

<div>
  <div>
    <x-drawer id="filters" label="Filters" side="right">
      <form action="/testimonials" method="GET">
        <div class="py-6">
          <div>
            <label for="search" class="block text-sm font-medium leading-6 text-fp-hi">Search</label>
            <input id="search" name="search" type="search" class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent sm:text-sm sm:leading-6" value="{{ request()->search }}">
          </div>
          <div>
            <label for="status" class="block text-sm font-medium leading-6 text-fp-hi">Status</label>
            <select id="status" name="status" class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent sm:text-sm sm:leading-6">
              <option @selected(request()->status == '') value="">All</option>
              <option @selected(request()->status == 'approved') value="approved">Approved</option>
              <option @selected(request()->status == 'pending') value="pending">Pending</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Submit</button>
        </div>
      </form>
    </x-drawer>

    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4">
        <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">Testimonials</h1>

        <div class="space-x-4 px-4 sm:px-0 flex items-center">
          <x-filters.button />
          <div>
            <modal labelledby="import-testimonials-title" :open-on-load="@js($errors->has('file'))" max-width="lg">
              <template v-slot:trigger="{ open }">
                <button type="button" v-on:click="open" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Import</button>
              </template>
              <template v-slot:default="{ close }">
                <form action="{{ route('testimonials.import') }}" method="POST" enctype="multipart/form-data" class="p-6">
                  @csrf

                  <h2 id="import-testimonials-title" class="text-lg font-semibold text-fp-hi">Import testimonials</h2>
                  <p class="mt-1 text-sm text-fp-low">Upload a CSV exported from Senja. Each row becomes an approved testimonial. Rows without any text, and rows that are already imported, are skipped.</p>

                  <div class="mt-4">
                    <label for="import-file" class="block text-sm font-medium text-fp-hi">CSV file</label>
                    <input id="import-file" name="file" type="file" accept=".csv,text/csv" required aria-describedby="import-file-error" class="mt-2 block w-full text-sm text-fp-low file:mr-4 file:rounded-md file:border-0 file:bg-fp-surface file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-fp-hi hover:file:bg-fp-surface">
                    @error('file')
                      <p id="import-file-error" class="mt-2 text-sm text-fp-danger">{{ $message }}</p>
                    @enderror
                  </div>

                  <div class="mt-6 flex justify-end gap-x-3">
                    <button type="button" v-on:click="close" class="rounded-md px-2.5 py-1.5 text-sm font-semibold text-fp-low hover:text-fp-hi">Cancel</button>
                    <button type="submit" class="rounded-md bg-fp-accent px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-accent-hover">Import</button>
                  </div>
                </form>
              </template>
            </modal>
          </div>
          <div>
            <a href="/testimonials/create" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Create Testimonial</a>
          </div>
        </div>
      </div>

      <section aria-labelledby="testimonials-heading" class="pb-24 pt-6">
        <div>
          <div>
            <div>
              <div>
                  <ul role="list" class="divide-y divide-white/5 overflow-hidden">
                    @forelse($testimonials as $testimonial)
                      <li class="relative hover:bg-fp-surface md:flex md:items-center md:justify-between">
                        <a href="/testimonials/{{ $testimonial->id }}" class="block min-w-0 flex-1 p-4 sm:px-6">
                          <div class="md:flex md:items-center md:justify-between">
                            <div class="flex items-start space-x-4">
                              <img class="h-10 w-10 rounded-full" src="{{ $testimonial->avatar_image }}" alt="{{ $testimonial->display_username }}">
                              <div>
                                <div class="flex justify-between text-sm items-center">
                                  <div class="text-sm text-fp-mid">
                                    <span class="">{{ $testimonial->created_at->diffForHumans() }}</span>
                                    @if($testimonial->contact)
                                      <span> by {{ $testimonial->contact->name ?? $testimonial->contact->email }}</span>
                                    @else
                                      <span> by {{ $testimonial->username }}</span>
                                    @endif
                                  </div>
                                </div>
                                <div>
                                  <div class="mt-2 md:flex md:items-center md:space-x-2">
                                    <h3 class="text-base text-fp-hi font-semibold">
                                      {{ $testimonial->title }}
                                    </h3>
                                    @if($testimonial->is_approved)
                                      <span class="inline-flex items-center gap-x-1.5 rounded-full px-1.5 py-0.5 text-xs font-medium text-fp-success">
                                        <svg class="h-1.5 w-1.5 fill-fp-success" viewBox="0 0 6 6" aria-hidden="true">
                                          <circle cx="3" cy="3" r="3" />
                                        </svg>
                                        Approved
                                      </span>
                                    @else
                                      <span class="inline-flex items-center gap-x-1.5 rounded-full px-1.5 py-0.5 text-xs font-medium text-fp-warning">
                                        <svg class="h-1.5 w-1.5 fill-fp-warning" viewBox="0 0 6 6" aria-hidden="true">
                                          <circle cx="3" cy="3" r="3" />
                                        </svg>
                                        Pending
                                      </span>
                                    @endif
                                  </div>
                                </div>
                                @if($testimonial->client->testimonial_rating_enabled && $testimonial->rating)
                                <div class="mt-2 flex items-center">
                                  @for($i = 1; $i <= 5; $i++)
                                    <svg class="h-4 w-4 {{ $i <= $testimonial->rating ? 'text-fp-warning' : 'text-fp-low' }}" fill="currentColor" viewBox="0 0 20 20">
                                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                  @endfor
                                  <span class="ml-2 text-sm text-fp-mid">{{ $testimonial->rating }}/5</span>
                                </div>
                                @endif
                              </div>
                            </div>
                          </div>
                        </a>
                        <div class="flex shrink-0 items-center gap-x-2 px-4 pb-4 sm:px-6 md:pb-0">
                          @if(!$testimonial->is_approved)
                            <form action="{{ route('testimonials.approve', $testimonial) }}" method="POST" class="inline">
                              @csrf
                              <button type="submit" class="rounded-md bg-fp-success/10 px-2 py-1 text-xs font-medium text-fp-success ring-1 ring-inset ring-fp-success/20 hover:bg-fp-success/10">
                                Approve
                              </button>
                            </form>
                          @else
                            <form action="{{ route('testimonials.decline', $testimonial) }}" method="POST" class="inline">
                              @csrf
                              @method('DELETE')
                              <button type="submit" class="rounded-md bg-fp-surface px-2 py-1 text-xs font-medium text-fp-mid ring-1 ring-inset ring-white/10 hover:bg-fp-elevated">
                                Remove approval
                              </button>
                            </form>
                          @endif
                        </div>
                      </li>
                    @empty
                      <li class="px-4 pt-6 pb-8">
                        <x-empty-state title="No Testimonials" description="Get started by adding a testimonial." href="/testimonials/create" action="Add testimonial" />
                      </li>
                    @endforelse
                  </ul>
                  <div class="mt-5 px-4 sm:px-0">
                      {{ $testimonials->withQueryString()->links() }}
                  </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
</x-app-layout>