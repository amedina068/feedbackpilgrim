<x-app-layout>
    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4">
        <div class="flex items-baseline space-x-4">
          <div>
            <div>
              <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">{{ $testimonial->title }}</h1>
            </div> 
            <p class="text-sm text-fp-mid">Created {{ $testimonial->created_at->format('M jS, Y') }}</p>
          </div>
          @if($testimonial->is_approved)
            <span class="inline-flex items-center rounded-md bg-fp-success/10 px-2 py-1 text-xs font-medium text-fp-success ring-1 ring-inset ring-fp-success/20">Approved</span>
          @else
            <span class="inline-flex items-center rounded-md bg-fp-warning/10 px-2 py-1 text-xs font-medium text-fp-warning ring-1 ring-inset ring-fp-warning/20">Pending</span>
          @endif
        </div>
        <div class="space-x-4 px-4 sm:px-0 flex items-center">
          @if(!$testimonial->is_approved)
            <form action="{{ route('testimonials.approve', $testimonial) }}" method="POST" class="inline">
              @csrf
              <button type="submit" class="rounded-md bg-fp-success px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-success">
                Approve
              </button>
            </form>
          @else
            <form action="{{ route('testimonials.decline', $testimonial) }}" method="POST" class="inline">
              @csrf
              @method('DELETE')
              <button type="submit" class="rounded-md bg-fp-elevated px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-fp-elevated">
                Remove Approval
              </button>
            </form>
          @endif
          <div>
            <a href="/testimonials/{{ $testimonial->id }}/edit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Edit</a>
          </div>
        </div>
      </div>

      <section aria-labelledby="testimonial-heading" class="pb-24 pt-6">
        <div class="grid grid-cols-1 gap-x-8 gap-y-10 xl:grid-cols-3">
          <div class="xl:col-span-2 ">
            <div class="overflow">
              <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 01-.923 1.785A5.969 5.969 0 006 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Testimonial
                      </h3>
                    </div>
                  </div>
                </div>
              <div class="border-t border-white/5">
                <div class="px-4 py-6">
                  @if($testimonial->client->testimonial_rating_enabled && $testimonial->rating)
                  <div class="flex items-center mb-4">
                    @for($i = 1; $i <= 5; $i++)
                      <svg class="h-5 w-5 {{ $i <= $testimonial->rating ? 'text-fp-warning' : 'text-fp-low' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                      </svg>
                    @endfor
                    <span class="ml-2 text-sm font-medium text-fp-mid">{{ $testimonial->rating }}/5</span>
                  </div>
                  @endif
                  <p class="text-fp-hi">{{ $testimonial->description }}</p>
                </div>
              </div>
            </div>
          </div>
          <div>
            <div class="mt-8">
              <div class="overflow">
                <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Author
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t px-4 sm:px-6 py-4 border-white/5">
                  <div class="flex items-center space-x-4">
                    <img src="{{ $testimonial->avatar_image }}" alt="" class="h-12 w-12 rounded-full">
                    <div>
                      <div class="text-sm font-medium text-fp-hi">{{ $testimonial->display_username }}</div>
                      @if($testimonial->subtitle)
                        <div class="text-sm text-fp-low">{{ $testimonial->subtitle }}</div>
                      @endif
                      @if($testimonial->contact)
                        <div class="text-xs text-fp-low mt-1">
                          <a href="/contacts/{{ $testimonial->contact_id }}" class="hover:underline">View contact</a>
                        </div>
                      @endif
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
</x-app-layout>