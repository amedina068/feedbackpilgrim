<x-public-layout>
  <x-public.breadcrumbs :client="$client">
    <x-public.breadcrumbs.item href="/posts">Posts</x-public.breadcrumbs.item>
    <x-public.breadcrumbs.item>New post</x-public.breadcrumbs.item>
  </x-public.breadcrumbs>
  <div class="mt-8">
    <x-public.heading>New post</x-public.heading>

    <form action="/posts" method="POST" enctype="multipart/form-data">
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
            <x-base.label for="content">Content</x-base.label>
            <div class="mt-1">
              <x-base.textarea id="content" name="content" required>{{ old('content') }}</x-base.textarea>
              @error('content')
                <p class="mt-1 text-sm font-medium text-fp-danger">{{ $message }}</p>
              @enderror
            </div>
          </div>
          @if($tags->isNotEmpty())
          <fieldset class="max-w-2xl">
            <legend class="text-sm/6 font-medium text-fp-mid">Tags</legend>
            <div class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-3">
              @foreach($tags as $tag)
              <label class="flex items-center gap-x-3 text-sm/6 font-medium text-fp-hi">
                <input id="tags-{{ $tag->id }}" name="tags[]" type="checkbox" value="{{ $tag->id }}" @checked(in_array($tag->id, old('tags', []))) class="size-4 shrink-0 rounded-sm border-white/10 bg-fp-surface text-fp-accent focus:ring-fp-accent/40">
                {{ $tag->title }}
              </label>
              @endforeach
            </div>
            @error('tags.*')
              <p class="mt-1 text-sm font-medium text-fp-danger">{{ $message }}</p>
            @enderror
          </fieldset>
          @endif
          <div class="max-w-2xl">
            <x-base.label for="attachments">Attachments</x-base.label>
            <p class="text-sm text-fp-low">Images, PDF, Word or text files. Up to 10 files, 10MB each.</p>
            <div class="mt-2">
              <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt" class="block w-full text-sm text-fp-mid file:mr-3 file:rounded-md file:border-0 file:bg-fp-accent/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-fp-accent hover:file:bg-fp-accent/20">
              @error('attachments')
                <p class="mt-1 text-sm font-medium text-fp-danger">{{ $message }}</p>
              @enderror
              @error('attachments.*')
                <p class="mt-1 text-sm font-medium text-fp-danger">{{ $message }}</p>
              @enderror
            </div>
          </div>
        </div>
        <div class="flex max-w-2xl justify-end gap-x-3">
          <a href="/posts" class="rounded-md bg-fp-surface px-3 py-2 text-sm font-medium text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-elevated">Cancel</a>
          <button type="submit" class="rounded-md bg-fp-accent px-3 py-2 text-sm font-medium text-white hover:bg-fp-accent-hover focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Create post</button>
        </div>
      </div>
    </form>
  </div>
</x-public-layout>
