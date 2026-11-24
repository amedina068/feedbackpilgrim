<x-app-layout>
    <div class="sm:px-6 lg:px-8">
      <div class="flex items-center justify-between gap-4 pb-4">
        <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">{{ $contact->name ?? 'N/A' }}</h1>
        <a href="{{ route('contacts.impersonate', $contact->id) }}" target="_blank" rel="noopener"
           class="inline-flex shrink-0 items-center gap-x-1.5 rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 text-fp-low">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
          </svg>
          View site as contact
        </a>
      </div>
      <section class="pb-24 pt-6">
        <div class="grid grid-cols-1 gap-x-8 gap-y-10 xl:grid-cols-3">
          <div class="xl:col-span-2">
            <div class="overflow-hidden rounded-xl border border-white/5">
              <div class="px-4 py-4 sm:px-6 bg-fp-surface">
                <div class="flex justify-between items-center">
                  <div class="flex space-x-2 items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <h3 class="text-lg font-medium leading-7 text-fp-mid">
                      Contact Information
                    </h3>
                    @if($contact->top_customer)
                      <span class="inline-flex items-center rounded-md bg-fp-success/10 px-2 py-1 text-xs font-medium text-fp-success ring-1 ring-inset ring-fp-success/20">Top Customer</span>
                    @endif
                  </div>
                  <div>
                    <a href="/contacts/{{ $contact->id }}/edit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Edit</a>
                  </div>
                </div>
              </div>
              <div class="border-t border-white/5">
                <dl class="divide-y divide-white/5">
                  <div class="grid gap-1 px-4 py-4 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-6 sm:px-6">
                    <dt class="text-sm font-medium text-fp-hi">Name</dt>
                    <dd class="text-sm text-fp-mid">{{ $contact->name }}</dd>
                  </div>
                  <div class="grid gap-1 px-4 py-4 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-6 sm:px-6">
                    <dt class="text-sm font-medium text-fp-hi">Email address</dt>
                    <dd class="min-w-0 truncate text-sm text-fp-mid">{{ $contact->email }}</dd>
                  </div>
                  <div class="grid gap-1 px-4 py-4 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-6 sm:px-6">
                    <dt class="text-sm font-medium text-fp-hi">LTV</dt>
                    <dd class="flex flex-wrap items-center gap-2 font-mono text-sm text-fp-mid tabular-nums">
                      ${{ number_format($contact->ltv, 2, '.', ',') }}
                      @if($contact->top_ltv)
                        <span class="rounded-md bg-fp-warning/10 px-1.5 py-0.5 text-xs font-medium text-fp-warning ring-1 ring-fp-warning/20 ring-inset">Top LTV</span>
                      @endif
                    </dd>
                  </div>
                  <div class="grid gap-1 px-4 py-4 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-6 sm:px-6">
                    <dt class="text-sm font-medium text-fp-hi">MRR</dt>
                    <dd class="flex flex-wrap items-center gap-2 font-mono text-sm text-fp-mid tabular-nums">
                      ${{ number_format($contact->mrr, 2, '.', ',') }}
                      @if($contact->top_mrr)
                        <span class="rounded-md bg-fp-accent/10 px-1.5 py-0.5 text-xs font-medium text-fp-accent ring-1 ring-fp-accent/20 ring-inset">Top MRR</span>
                      @endif
                    </dd>
                  </div>
                  @foreach($contact->metadataArray as $key => $field)
                  <div class="grid gap-1 px-4 py-4 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-6 sm:px-6">
                    <dt class="text-sm font-medium text-fp-hi">{{ Str::of($key)->replace('_', ' ')->title() }}</dt>
                    <dd class="text-sm text-fp-mid">{{ $field }}</dd>
                  </div>
                  @endforeach
                </dl>
              </div>
            </div>

            <div class="mt-8">
              <div class="overflow-hidden rounded-xl border border-white/5">
                <div class="bg-fp-surface px-4 py-4 sm:px-6">
                  <div class=" flex flex-wrap items-center justify-between sm:flex-nowrap">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                      </svg>
                      <h3 class="text-lg font-medium leading-7 text-fp-mid">
                        Notes
                      </h3>
                    </div>
                    <div>
                      <a href="/contacts/{{ $contact->id }}/notes" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Edit Notes</a>
                    </div>
                  </div>
                </div>
                <div class="bg-fp-surface border-t border-white/5 px-4 pt-6 pb-8 sm:px-6">
                  @if($contact->notes)
                    <x-markdown class="prose max-w-full">{{ $contact->notes }}</x-markdown>
                  @else
                    <x-empty-state title="No Notes" description="Get started by adding notes to this post." href="/contacts/{{ $contact->id }}/notes" action="Add notes" />
                  @endif
                </div>
              </div>
            </div>
            
            <div class="mt-8">
              <ul role="list" class="rounded-xl border border-white/5 divide-y mt-8 divide-white/5 overflow-hidden">
                <div class="bg-fp-surface px-4 py-4 sm:px-6">
                  <div class="flex flex-wrap items-baseline justify-between sm:flex-nowrap">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                      </svg>
                      <h3 class="text-lg font-medium leading-7 text-fp-mid">
                        Posts
                      </h3>
                    </div>
                    <div>
                      <a href="/posts/create?contact_id={{ $contact->id }}" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Create Post</a>
                    </div>
                  </div>
                </div>
                @forelse($contact->posts as $post)
                  <li class="relative gap-x-6 px-4 py-5 hover:bg-fp-surface sm:px-6">
                    <a href="/posts/{{ $post->id }}">
                      <div class="flex justify-between text-sm items-baseline">
                        <span>{{ $post->created_at->diffForHumans() }}</span>
                        <div>
                          {{ $post->stage_name }}
                        </div>
                      </div>
                      <div>
                          <h3 class="mt-4 text-lg font-semibold">
                              {{$post->title }}
                          </h3>
                          <p class="text-base mt-2">{{ $post->content }}</p>
                      </div>
                    </a>
                  </li>
                @empty
                <li class="px-4 pt-6 pb-8">
                  <x-empty-state title="No Posts" description="Get started by adding posts to this contact." href="/posts/create?contact_id={{ $contact->id }}" action="Add posts" />
                </li>
                @endforelse
              </ul>
            </div>
          </div>
          <div>
            <div>
              <div class="overflow-hidden">
                <div class="px-4 py-4">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Sessions
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t px-4 py-4 border-white/5">
                  <div class="space-y-2 text-fp-mid">
                    @if($sessions || $contact->lastLogin)
                      @if($sessions)
                      <div>
                        <p class="text-sm ">{{ $sessions }} Sessions</p>
                      </div>
                      @endif
                      @if($contact->lastLogin)
                        <div>
                          <p class="text-sm ">Last login at {{ $contact->lastLogin->format('H:i:s \o\n M jS, Y') }}</p>
                        </div>
                      @endif
                    @else
                      <p class="text-left text-sm text-fp-mid">This contact doesn't have any sessions information</p>
                    @endif
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-8">
              <div class="overflow-hidden">
                <div class="px-4 py-4">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Tags
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t px-4 py-4 border-white/5">
                  <div class="inline-flex flex-wrap -mb-2">
                    @foreach($contact->tags as $tag)
                      <span class="mb-2 mr-2 inline-flex items-center rounded-md bg-fp-surface px-2 py-1 text-xs font-medium text-fp-mid ring-1 ring-inset ring-white/10">{{ $tag->title }}</span>
                    @endforeach
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-8">
              <div class="overflow-hidden">
                <div class="px-4 py-4">
                  <div class="flex justify-between items-baseline">
                    <div class="flex justify-between items-baseline">
                      <div class="flex space-x-2 items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" />
                        </svg>
                        <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                          <span class="text-lg font-semibold tracking-wide {{ $contact->comments_count > 0 ? 'text-fp-hi' : 'text-fp-low' }}">{{ $contact->comments_count }}</span>
                          Comments
                        </h3>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="border-t border-white/5">
                  <dl class="divide-y divide-white/5">
                    @foreach($contact->comments as $comment)
                      <div class="px-4 py-4 ">
                        <p class="text-sm text-fp-mid">Commented <a class="hover:underline text-fp-hi font-medium " href="/posts/{{ $comment->commentable_id }}">{{ Str::limit($comment->commentable->title, 32) }}</a> {{ $comment->created_at->diffForHumans() }}</p>
                      </div>
                    @endforeach
                    @if($contact->comments->count() < $contact->comments_count)
                      <div class="px-4 py-4 ">
                          <p class="text-sm text-fp-low">Showing the {{ $contact->comments->count() }} latest of {{ $contact->comments_count }} comments.</p>
                        </div>
                    @endif
                  </dl>
                </div>
              </div>
            </div>
            <div class="mt-8">
              <div class="overflow-hidden">
                <div class="px-4 py-4">
                  <div class="flex justify-between items-baseline">
                    <div class="flex justify-between items-baseline">
                      <div class="flex space-x-2 items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" />
                        </svg>
                        <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                          <span class="text-lg font-semibold tracking-wide {{ $contact->votes_count > 0 ? 'text-fp-hi' : 'text-fp-low' }}">{{ $contact->votes_count }}</span>
                          Votes
                        </h3>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="border-t border-white/5">
                  <dl class="divide-y divide-white/5">
                    @foreach($contact->votes as $vote)
                      <div class="px-4 py-4 ">
                        <p class="text-sm text-fp-mid">{{ $vote->negative ? 'Downvoted' : 'Upvoted' }} 
                          @if($vote->votable_type === 'App\\Models\\Post')
                            <a class="hover:underline text-fp-hi font-medium " href="/posts/{{ $vote->votable_id }}">{{ Str::limit($vote->votable->title, 32) }}</a>
                          @elseif($vote->votable_type === 'App\\Models\\Testimonial')
                            <a class="hover:underline text-fp-hi font-medium " href="/testimonials/{{ $vote->votable_id }}">{{ Str::limit($vote->votable->title, 32) }}</a>
                          @endif
                          {{ $vote->created_at->diffForHumans() }}</p>
                      </div>
                    @endforeach
                    @if($contact->votes->count() < $contact->votes_count)
                    <div class="px-4 py-4 ">
                      <p class="text-sm text-fp-low">Showing the {{ $contact->votes->count() }} latest of {{ $contact->votes_count }} votes.</p>
                    </div>
                    @endif
                  </dl>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
</x-app-layout>
