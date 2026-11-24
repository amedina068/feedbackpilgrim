<x-app-layout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-fp-surface sm:rounded-lg">
          <div class="px-4 py-6 sm:px-6">
            <h3 class="text-base font-semibold leading-7 text-fp-hi">Edit Contact Information</h3>
          </div>
          <div class="border-t border-white/5">
            <form action="/contacts/{{ $contact->id }}" method="POST">
              @csrf
              @method('PATCH')
              <div class="divide-y divide-white/5">
                <div class="flex items-center gap-x-3 px-4 py-5 sm:px-6">
                  <input id="top_customer" name="top_customer" type="checkbox" @checked(old('top_customer', $contact->top_customer)) class="size-4 shrink-0 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                  <label for="top_customer" class="text-sm/6 font-medium text-fp-hi">Top Customer</label>
                </div>
                @foreach ([
                    'name' => ['Name', 'text', 'John Doe'],
                    'email' => ['Email address', 'text', 'johndoe@example.com'],
                    'ltv' => ['LTV', 'number', '100.00'],
                    'mrr' => ['MRR', 'number', '100.00'],
                ] as $field => [$label, $type, $placeholder])
                  <div class="grid gap-2 px-4 py-5 sm:grid-cols-[12rem_minmax(0,1fr)] sm:items-start sm:gap-6 sm:px-6">
                    <label for="{{ $field }}" class="text-sm/9 font-medium text-fp-hi">{{ $label }}</label>
                    <div class="flex max-w-sm flex-col gap-y-1">
                      <x-base.input
                        :type="$type"
                        :min="$type === 'number' ? '0.00' : null"
                        :step="$type === 'number' ? '0.01' : null"
                        :placeholder="$placeholder"
                        :value="old($field, $contact->{$field})"
                        :id="$field"
                        :name="$field"
                      />
                      @error($field)
                        <p class="text-sm font-medium text-fp-danger">{{ $message }}</p>
                      @enderror
                    </div>
                  </div>
                @endforeach
                @foreach($contact->metadataArray as $key => $field)
                  <contact-form-dynamic-row field-key="{{ $key }}" field="{{ $field }}"></contact-form-dynamic-row>
                @endforeach
                <contact-form-custom-fields></contact-form-custom-fields>
              </div>

            <div>
              <div class="max-w-4xl px-4 sm:px-6 py-6">
              <fieldset>
                <legend class="block text-sm font-medium leading-6 text-fp-mid">Tags</legend>
                <div class="grid grid-cols-2 sm:grid-cols-4 mt-2 gap-x-4 gap-y-4 w-full">
                  @forelse($tags as $tag)
                  <div class="flex items-start gap-x-3">
                    <input id="tags-{{ $tag->id }}" name="tags[]" type="checkbox" value="{{ $tag->id }}" @checked(in_array($tag->id, old('tags', $contact->tags->pluck('id')->all()))) class="mt-1 size-4 shrink-0 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
                    <div class="min-w-0 text-sm/6">
                      <label for="tags-{{ $tag->id }}" class="font-medium text-fp-hi">{{ $tag->title }}</label>
                    </div>
                  </div>
                  @empty
                  <div class="col-span-full">
                    <p class="text-sm text-fp-mid">
                      <span>You have no tags. </span>
                      <a href="/tags/create" class="hover:underline font-semibold text-fp-hi">Create a tag</a>
                    </p>
                  </div>
                  @endforelse
                </div>
              </fieldset>
              </div>
              <div class="px-4 py-6 flex justify-end sm:px-6 space-x-2">
                <a href="/contacts/{{ $contact->id }}" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Cancel</a>
                <button type="submit" class="rounded-md bg-fp-accent px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-accent focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fp-accent">Save</button>
              </div>
            </div>
            </form>
          </div>
        </div>
      </div>
    </div>
</x-app-layout>
