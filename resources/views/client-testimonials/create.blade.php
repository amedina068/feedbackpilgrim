<x-public-layout>
  <x-public.breadcrumbs :client="$client">
    <x-public.breadcrumbs.item href="/testimonials">Testimonials</x-public.breadcrumbs.item>
    <x-public.breadcrumbs.item>New testimonial</x-public.breadcrumbs.item>
  </x-public.breadcrumbs>
  <div class="mt-8">
    <x-public.heading>New testimonial</x-public.heading>

    <form action="/testimonials" method="POST">
      @csrf
      <div>
        <div class="space-y-6 py-6">
          <div class="max-w-md">
            <x-base.label for="title">Title</x-base.label>
            <div class="mt-1">
              <x-base.input type="text" id="title" name="title" :value="old('title')" required/>
                @error('title')
                  <p class="mt-1 text-sm font-medium text-fp-danger">{{ $message }}</p>
                @enderror
            </div>
          </div>
          <div class="w-full max-w-2xl">
            <x-base.label for="description">Description</x-base.label>
            <div class="mt-1">
              <x-base.textarea id="description" name="description" required>{{ old('description') }}</x-base.textarea>
                @error('description')
                  <p class="mt-1 text-sm font-medium text-fp-danger">{{ $message }}</p>
                @enderror
            </div>
          </div>
          @if($client->testimonial_rating_enabled)
          <div class="max-w-xs">
            <x-base.label for="rating">Rating</x-base.label>
            <div class="mt-1">
              <x-base.select name="rating" id="rating" required>
                <option value="">Select rating</option>
                @for($i = 1; $i <= 5; $i++)
                  <option value="{{ $i }}" @selected(old('rating') == $i)>{{ $i }} {{ $i == 1 ? 'star' : 'stars' }}</option>
                @endfor
              </x-base.select>
              @error('rating')
                <p class="mt-1 text-sm font-medium text-fp-danger">{{ $message }}</p>
              @enderror
            </div>
          </div>
          @endif
        </div>
        <div class="flex max-w-2xl justify-end gap-x-3">
          <a href="/testimonials" class="rounded-md bg-fp-surface px-3 py-2 text-sm font-medium text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-elevated">Cancel</a>
          <button type="submit" class="rounded-md bg-fp-accent px-3 py-2 text-sm font-medium text-white hover:bg-fp-accent-hover focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Save</button>
        </div>
      </div>
    </form>
  </div>
</x-public-layout>
