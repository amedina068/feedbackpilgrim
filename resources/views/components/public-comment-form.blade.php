<div class="mt-4">
  @if(auth()->guard('contact')->check() && auth()->guard('contact')->user()->client_id == $client->id)
    <form action="/comments" method="POST" enctype="multipart/form-data" class="flex flex-col gap-3">
      @csrf
      <input type="hidden" name="model_id" value="{{ $model->id }}">
      <input type="hidden" name="model_type" value="{{ get_class($model) }}">

      <div class="flex items-start gap-x-3">
        <img class="size-8 shrink-0 rounded-full bg-fp-surface outline-1 -outline-offset-1 outline-white/10" src="{{ auth()->guard('contact')->user()->avatar }}" alt="">
        <div class="min-w-0 flex-1">
          <label for="content" class="sr-only">Add your comment</label>
          <x-base.textarea id="content" name="content" rows="4" placeholder="Add your comment..." required>{{ old('content') }}</x-base.textarea>
          @error('content')
            <p class="mt-1 text-sm font-medium text-fp-danger">{{ $message }}</p>
          @enderror
        </div>
      </div>

      <div class="flex flex-wrap items-end justify-between gap-3 sm:pl-11">
        <div class="min-w-0">
          <label for="photos" class="text-xs font-medium text-fp-mid">Add photos</label>
          <input type="file" name="photos[]" id="photos" multiple accept="image/jpeg,image/png,image/gif,image/webp" class="mt-1 w-full text-sm text-fp-mid file:mr-3 file:rounded-md file:border-0 file:bg-fp-accent/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-fp-accent hover:file:bg-fp-accent/20">
          @error('photos')
            <p class="mt-1 text-sm font-medium text-fp-danger">{{ $message }}</p>
          @enderror
          @error('photos.*')
            <p class="mt-1 text-sm font-medium text-fp-danger">{{ $message }}</p>
          @enderror
        </div>
        <button type="submit" class="shrink-0 rounded-md bg-fp-accent px-3 py-2 text-sm font-medium text-white hover:bg-fp-accent-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Comment</button>
      </div>
    </form>
  @else
    <p class="text-sm text-fp-mid">To comment, open this board from your {{ $client->name }} account.</p>
  @endif
</div>
