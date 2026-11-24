<x-app-layout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto">
        <div class="overflow-hidden bg-fp-surface sm:rounded-lg">
          <div class="px-4 py-6 sm:px-6">
            <div class="flex justify-between items-baseline">
              <h3 class="text-base font-semibold leading-7 text-fp-hi">Edit Testimonial</h3>
            </div>
          </div>
          <div class="border-t border-white/5">
            <form action="/testimonials/{{ $testimonial->id }}" method="POST">
              @csrf
              @method('PATCH')
              <div>
                <div class="px-4 md:px-6 space-y-6 py-6">
                  <div class="max-w-xs">
                    <x-base.label for="contact_id">Contact</x-base.label>
                    <x-base.select name="contact_id" id="contact_id">
                      <option value="">No contact</option>
                      @foreach($contacts as $contactItem)
                        <option value="{{ $contactItem->id }}" @selected(old('contact_id', $testimonial->contact_id) == $contactItem->id)>{{ $contactItem->email }}</option>
                      @endforeach
                    </x-base.select>
                  </div>
                  <div class="max-w-md">
                    <x-base.label for="title">Title</x-base.label>
                    <div class="mt-1">
                      <x-base.input type="text" id="title" name="title" :value="old('title', $testimonial->title)" required/>
                        @error('title')
                          <div class="mt-1">
                            <p class="text-sm text-fp-danger font-medium">{{ $message }}</p>
                          </div>
                        @enderror
                    </div>
                  </div>
                  <div class="max-w-md">
                    <x-base.label for="description">Description</x-base.label>
                    <div class="mt-1">
                      <x-base.textarea id="description" name="description" required>{{ old('description', $testimonial->description) }}</x-base.textarea>
                        @error('description')
                          <div class="mt-1">
                            <p class="text-sm text-fp-danger font-medium">{{ $message }}</p>
                          </div>
                        @enderror
                    </div>
                  </div>
                  @if($client->testimonial_rating_enabled)
                  <div class="max-w-xs">
                    <x-base.label for="rating">Rating</x-base.label>
                    <x-base.select name="rating" id="rating" required>
                      <option value="">Select rating</option>
                      @for($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" @selected(old('rating', $testimonial->rating) == $i)>{{ $i }} {{ $i == 1 ? 'star' : 'stars' }}</option>
                      @endfor
                    </x-base.select>
                    @error('rating')
                      <div class="mt-1">
                        <p class="text-sm text-fp-danger font-medium">{{ $message }}</p>
                      </div>
                    @enderror
                  </div>
                  @endif
                  <div class="max-w-md">
                    <x-base.label for="username">Username</x-base.label>
                    <div class="mt-1">
                      <x-base.input type="text" id="username" name="username" :value="old('username', $testimonial->username)" required/>
                        @error('username')
                          <div class="mt-1">
                            <p class="text-sm text-fp-danger font-medium">{{ $message }}</p>
                          </div>
                        @enderror
                    </div>
                  </div>
                  <div class="max-w-md">
                    <x-base.label for="subtitle">Subtitle</x-base.label>
                    <div class="mt-1">
                      <x-base.input type="text" id="subtitle" name="subtitle" :value="old('subtitle', $testimonial->subtitle)"/>
                        @error('subtitle')
                          <div class="mt-1">
                            <p class="text-sm text-fp-danger font-medium">{{ $message }}</p>
                          </div>
                        @enderror
                    </div>
                  </div>
                </div>
                <div class="px-4 md:px-6 py-6 flex justify-end sm:px-6 space-x-2">
                  <a href="/testimonials/{{ $testimonial->id }}" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Cancel</a>
                  <button type="submit" class="rounded-md bg-fp-accent px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Save</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
</x-app-layout>