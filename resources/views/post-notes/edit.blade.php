<x-app-layout>
    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4">
        <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">{{ $post->title }}</h1>
      </div>
      <section class="pb-24 pt-6">
        <div class="grid grid-cols-1 gap-x-8 gap-y-10 lg:grid-cols-5">
          <div></div>
          <div class="lg:col-span-4">
            <div>
              <div class="divide-y divide-white/5 overflow-hidden bg-fp-surface ring-1 ring-white/10 sm:rounded-xl">
                <div class="bg-fp-surface px-4 py-5 sm:px-6">
                  <div class="flex flex-wrap items-center justify-between sm:flex-nowrap">
                    <div class="flex space-x-2 items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                      Notes
                    </h3>
                  </div>
                  </div>
                </div>
                <div class="bg-fp-surface px-4 py-5 sm:px-6">
                  <form action="/posts/{{ $post->id }}/notes" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="mt-4">
                      <textarea rows="10" name="notes" id="notes" class="text-lg block w-full rounded-md border-0 py-1.5 text-fp-hi ring-1 ring-inset ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-inset focus:ring-fp-accent sm:leading-6">{{ $post->notes}}</textarea>
                    </div>
                    <div class="flex justify-end mt-4 space-x-4">
                      <a href="/posts/{{ $post->id }}" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Cancel </a>
                        <button type="submit" class="rounded-md bg-fp-accent px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Update</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
            <div class="mt-8">
              <div class="overflow-hidden bg-fp-surface sm:rounded-lg">
                <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Content
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t border-white/5">
                  <div class="px-4 py-6">
                    <x-markdown class="prose max-w-full">{{ $post->content }}</x-markdown>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
</x-app-layout>
