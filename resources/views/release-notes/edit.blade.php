<x-app-layout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto">
        <div class="overflow-hidden bg-fp-surface sm:rounded-lg">
          <div class="px-4 py-6 sm:px-6">
            <h3 class="text-base font-semibold leading-7 text-fp-hi">Edit Release Note</h3>
          </div>
          <div class="border-t border-white/5">
            <form action="/release-notes/{{ $releaseNote->id }}" method="POST">
              @csrf
              @method('PATCH')
              <div class="px-4 md:px-6 space-y-6 py-6">
                <div class="max-w-md">
                  <x-base.label for="title">Title</x-base.label>
                  <div class="mt-1">
                    <x-base.input type="text" id="title" name="title" :value="old('title', $releaseNote->title)"/>
                    @error('title')
                      <p class="mt-1 text-sm text-fp-danger font-medium">{{ $message }}</p>
                    @enderror
                  </div>
                </div>
                <div class="max-w-3xl">
                  <x-base.label for="content">Content</x-base.label>
                  <div class="mt-1">
                    <x-base.textarea name="content" id="content" rows="20">{{ old('content', $releaseNote->content) }}</x-base.textarea>
                    @error('content')
                      <p class="mt-1 text-sm text-fp-danger font-medium">{{ $message }}</p>
                    @enderror
                  </div>
                </div>
              </div>
              <div class="px-4 md:px-6 py-6 flex justify-end sm:px-6 space-x-2">
                <a href="/release-notes/{{ $releaseNote->id }}" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Cancel</a>
                <button type="submit" class="rounded-md bg-fp-accent px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Save</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
</x-app-layout>
