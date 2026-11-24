@props(['action'])

<form action="{{ $action }}" method="POST" class="flex flex-col gap-2">
  @csrf
  <label for="content" class="sr-only">Add your comment</label>
  <x-base.textarea id="content" name="content" rows="5" placeholder="Add your comment..." required>{{ old('content') }}</x-base.textarea>
  @error('content')
    <p class="text-sm font-medium text-fp-danger">{{ $message }}</p>
  @enderror
  <button type="submit" class="self-end rounded-md bg-fp-accent px-3 py-2 text-sm font-medium text-white hover:bg-fp-accent-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Post</button>
</form>
