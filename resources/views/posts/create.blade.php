<x-app-layout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto">
        <div class="overflow-hidden bg-fp-surface sm:rounded-lg">
          <div class="px-4 py-6 sm:px-6">
            <div class="flex justify-between items-baseline">
              <h3 class="text-base font-semibold leading-7 text-fp-hi">Create Post</h3>
            </div>
          </div>
          <div class="border-t border-white/5">
            <form action="/posts" method="POST">
              @csrf
              <div>
                <div class="px-4 md:px-6 space-y-6 py-6">
                  <div class="max-w-xs">
                    <x-base.label for="contact_id">Contact</x-base.label>
                    <x-base.select name="contact_id" id="contact_id">
                      <option value="">No contact</option>
                      @foreach($contacts as $contactItem)
                        <option value="{{ $contactItem->id }}" @selected(request()->contact_id == $contactItem->id)>{{ $contactItem->email }}</option>
                      @endforeach
                    </x-base.select>
                  </div>
                  <div class="max-w-md">
                    <x-base.label for="title">Title</x-base.label>
                    <div class="mt-1">
                      <x-base.input type="text" id="title" name="title" :value="old('title')"/>
                        @error('title')
                          <div class="mt-1">
                            <p class="text-sm text-fp-danger font-medium">{{ $message }}</p>
                          </div>
                        @enderror
                    </div>
                  </div>
                  <div class="max-w-md">
                    <x-base.label for="content">Content</x-base.label>
                    <div class="mt-1">
                      <x-base.textarea id="content" name="content">{{ old('content') }}</x-base.textarea>
                    </div>
                  </div>
                  <div class="max-w-4xl">
                    <x-base.label for="title">Tags</x-base.label>
                    <div class="grid grid-cols-4 mt-2 gap-x-4 gap-y-4 w-full">
                      @forelse($tags as $tag)
                      <div class="flex items-start gap-x-3">
                        <input id="tags-{{ $tag->id }}" aria-describedby="tags-description" name="tags[]" type="checkbox" value="{{ $tag->id }}" class="mt-1 size-4 shrink-0 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                        <div class="min-w-0 text-sm/6">
                          <label for="tags-{{ $tag->id }}" class="font-medium text-fp-hi">{{ $tag->title }}</label>
                        </div>
                      </div>
                      @empty
                      <div class="col-span-4">
                        <p class="text-sm text-fp-mid">
                          <span>You have no tags. </span>
                          <a href="/tags/create" class="hover:underline font-semibold text-fp-hi">Create a tag</a>
                        </p>
                      </div>
                      @endforelse
                    </div>
                  </div> 
                </div>
                <div class="px-4 md:px-6 py-6 flex justify-end sm:px-6 space-x-2">
                  <a href="/posts" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Cancel</a>
                  <button type="submit" class="rounded-md bg-fp-accent px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Save</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
</x-app-layout>
