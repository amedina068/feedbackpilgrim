<x-app-layout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto">
        <div class="overflow-hidden bg-fp-surface sm:rounded-lg">
          <div class="px-4 py-6 sm:px-6">
            <div class="flex justify-between items-baseline">
              <h3 class="text-base font-semibold leading-7 text-fp-hi">Create Tag</h3>
            </div>
          </div>
          <div class="border-t border-white/5">
            <form action="/tags" method="POST">
              @csrf
              <div>
                <div class="px-4 md:px-6 space-y-6 py-6">
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
                </div>
                <div class="px-4 md:px-6 py-6 flex justify-end sm:px-6 space-x-2">
                  <a href="/tags" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Cancel</a>
                  <button type="submit" class="rounded-md bg-fp-accent px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Save</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
</x-app-layout>
