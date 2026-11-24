<x-app-layout>
    <div class="sm:px-6 lg:px-8">
      <div class="flex items-baseline justify-between sm:border-b border-white/5 pb-4">
        <div class="flex items-baseline space-x-4">
          <div>
            <div>
              <h1 class="px-4 sm:px-0 text-3xl font-semibold tracking-tight text-fp-hi">{{ $post->title }}</h1>
            </div> 
            @if($post->published_at)
              <p class="text-sm text-fp-mid">Published at {{ $post->published_at->format('H:i \o\n M jS, Y') }}</p>
            @endif
          </div>
          @if($post->inProgress())
              <span class="inline-flex items-center rounded-md bg-fp-warning/10 px-2 py-1 text-xs font-medium text-fp-warning ring-1 ring-inset ring-fp-warning/20">In Progress</span>
            @endif
            @if($post->pending())
              <span class="inline-flex items-center rounded-md bg-fp-warning/10 px-2 py-1 text-xs font-medium text-fp-warning ring-1 ring-inset ring-fp-warning/20">Pending</span>
            @endif
            @if($post->completed())
              <span class="inline-flex items-center rounded-md bg-fp-warning/10 px-2 py-1 text-xs font-medium text-fp-warning ring-1 ring-inset ring-fp-warning/20">Completed</span>
            @endif
            @if($post->archived_at)
              <span class="inline-flex items-center rounded-md bg-fp-accent/10 px-2 py-1 text-xs font-medium text-fp-accent ring-1 ring-inset ring-fp-accent/20">Archived</span>
            @endif
        </div>
        <div class="space-x-4 px-4 sm:px-0 flex items-center">
          <div>
            <a href="/posts/{{ $post->id }}/edit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Edit</a>
          </div>
        </div>
      </div>

      <section class="pb-24 pt-6">
        <div class="grid grid-cols-1 gap-x-8 gap-y-10 xl:grid-cols-3">
          <div class="xl:col-span-2 ">
            <div class="overflow">
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
                @if($post->getMedia('attachments')->isNotEmpty())
                  <div class="border-t border-white/5 px-4 py-6">
                    <h4 class="text-sm font-semibold text-fp-hi">Attachments</h4>
                    <ul role="list" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                      @foreach($post->getMedia('attachments') as $media)
                        <li>
                          <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener" class="group block">
                            @if(Str::startsWith($media->mime_type, 'image/'))
                              <img src="{{ $media->getUrl() }}" alt="{{ $media->name }}" class="h-32 w-full rounded-lg object-cover ring-1 ring-white/5 group-hover:opacity-90">
                            @else
                              <div class="flex h-32 w-full items-center justify-center rounded-lg bg-fp-surface ring-1 ring-white/5 group-hover:bg-fp-elevated">
                                <span class="px-2 text-center text-xs font-medium text-fp-mid break-all">{{ $media->name }}.{{ $media->extension }}</span>
                              </div>
                            @endif
                          </a>
                        </li>
                      @endforeach
                    </ul>
                  </div>
                @endif
              </div>
            </div>
            <div class="mt-8">
              <div class="overflow">
              <div class="px-4 py-4 sm:px-6">
                <div class="flex justify-between items-center">
                  <div class="flex space-x-2 items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                      Notes
                    </h3>
                  </div>
                  @if($post->notes)
                    <div>
                      <a href="/posts/{{ $post->id }}/notes" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Edit Notes</a>
                    </div>
                  @endif
                </div>
              </div>
              <div class="border-t border-white/5">
                <div class="px-4 pt-6 pb-8">
                  @if($post->notes)
                    <x-markdown class="prose max-w-full">{{ $post->notes }}</x-markdown>
                  @else
                    <x-empty-state title="No Notes" description="Get started by adding notes to this post." href="/posts/{{ $post->id }}/notes" action="Add notes" />
                  @endif
                </div>
              </div>
            </div>
            </div>
            <div class="mt-8">
              <div class="overflow">
                <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        {{ $post->comments_count }} Comments
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t border-white/5">
                  <dl class="divide-y divide-white/5">
                    <div class="pb-8">
                      <x-comment-form action="/posts/{{ $post->id }}/comments" />
                    </div>
                    @forelse($post->comments as $comment)
                      <div class="px-4 py-4">
                        <p class="text-xs text-fp-mid">
                          <a class="hover:underline text-fp-hi font-medium " href="/contacts/{{ $comment->contact_id }}">{{ $comment->contact->name ?? $comment->contact->email }}</a> commented {{ $comment->created_at->diffForHumans() }}
                        </p>
                        <p class="mt-2 text-sm text-fp-mid">
                          {{ $comment->content }}
                        </p>
                        @if($comment->getMedia('photos')->isNotEmpty())
                          <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($comment->getMedia('photos') as $photo)
                              <a href="{{ $photo->getUrl() }}" target="_blank" rel="noopener">
                                <img src="{{ $photo->getUrl() }}" alt="{{ $photo->name }}" class="h-20 w-20 rounded-lg object-cover ring-1 ring-white/5 hover:opacity-90">
                              </a>
                            @endforeach
                          </div>
                        @endif
                      </div>
                    @empty
                      <x-empty-state class="mx-4 mt-6 mb-8" title="No Comments" description="Start the conversation with the first comment." />
                    @endforelse
                    @if($post->comments_count > 5)
                      <div class="px-4 py-4 ">
                        <p class="text-xs text-fp-mid">
                          <a class="hover:underline text-fp-hi font-medium " href="/posts/{{ $post->id }}/comments">View all comments</a>
                        </p>
                      </div>
                    @endif
                  </dl>
                </div>
              </div> 
            </div>
          </div>
          <div>
            <div class="inline-flex w-full rounded-md">
              @if($post->published_at)
                <form action="/posts/{{ $post->id }}/publication" method="POST" class="w-full">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="justify-center w-full relative flex items-center rounded-l-md bg-fp-surface px-3 py-2 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface focus:z-10">Unpublish</button> 
                </form>
              @endif
              @if(! $post->published_at)
                <form action="/posts/{{ $post->id }}/publication" method="POST" class="w-full">
                  @csrf
                  <button type="submit" class="justify-center w-full relative flex items-center rounded-l-md bg-fp-surface px-3 py-2 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface focus:z-10">Publish</button> 
                </form>
              @endif
              <div class="relative -ml-px block">
                <base-dropdown>
                  <template v-slot:handler>
                    <span class="relative inline-flex items-center rounded-r-md bg-fp-surface px-2 py-2 text-fp-low ring-1 ring-inset ring-white/10 hover:bg-fp-surface focus:z-10">
                      <span class="sr-only">Open options</span>
                      <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                      </svg>
                    </span>
                  </template>
                <template v-slot:default>
                  @foreach($stages as $stage)
                    @if($stage->id !== $post->stage_id)
                    <menu-item v-slot="{ active }" as="form" action="/posts/{{ $post->id }}/status" method="POST">
                      @csrf
                      @method('PATCH')
                      <input type="hidden" name="stage_id" value="{{ $stage->id }}">
                      <button
                        :class="[active ? 'bg-white/5 text-fp-hi' : 'text-fp-mid', 'flex items-center w-full px-4 py-2 text-sm']"
                      >
                        <span class="mr-2 inline-block h-2 w-2 rounded-full" style="background-color: {{ $stage->color }}"></span>
                        {{ $stage->name }}
                      </button>
                    </menu-item>
                    @endif
                  @endforeach
                  @if($post->archived_at)
                  <menu-item v-slot="{ active }" id="unarchive-form" as="form" action="/posts/{{ $post->id }}/archive" method="POST">
                    @csrf
                    @method('DELETE')
                    <button
                      :class="[active ? 'bg-white/5 text-fp-hi' : 'text-fp-mid', 'flex items-center w-full px-4 py-2 text-sm']"
                    >Unarchive</button>
                  </menu-item>
                  @endif
                  @if(! $post->archived_at)
                  <menu-item v-slot="{ active }" id="archive-form" as="form" action="/posts/{{ $post->id }}/archive" method="POST">
                    @csrf
                    <button
                      :class="[active ? 'bg-white/5 text-fp-hi' : 'text-fp-mid', 'flex items-center w-full px-4 py-2 text-sm']"
                    >Archive</button>
                  </menu-item>
                  @endif
                </template>
                </base-dropdown>
              </div>
            </div>

            <div class="mt-8">
              <div class="overflow">
                <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Contact
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t px-4 sm:px-6 py-4 border-white/5">
                  @if($post->contact)
                    <div>
                      <img src="{{ $post->contact->avatar }}" alt="" class="h-12 w-12 rounded-full">
                      <div class="mt-4 space-y-2 text-sm text-fp-mid">
                        <p><a href="/contacts/{{ $post->contact_id }}" class="underline">{{ $post->contact->name }}</a></p>
                        <p>{{ $post->contact->name ?? $post->contact->email }}</p>
                        <p>Created {{ $post->contact->created_at->diffForHumans() }}</p>
                      </div>
                    </div>
                  @else
                    <p class=" text-sm text-fp-mid">This post wasn't requested by a contact</p>
                  @endif
                </div>
              </div>
            </div>
            <div class="mt-8">
              <div class="overflow">
                <div class="px-4 py-4 sm:px-6">
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
                    @foreach($post->tags as $tag)
                      <span class="mb-2 mr-2 inline-flex items-center rounded-md bg-fp-surface px-2 py-1 text-xs font-medium text-fp-mid ring-1 ring-inset ring-white/10">{{ $tag->title }}</span>
                    @endforeach
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-8">
              <div class="overflow">
                <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        <span class="text-lg font-semibold tracking-wide {{ $post->votes_count > 0 ? 'text-fp-hi' : 'text-fp-low' }}">{{ $post->votes_count }}</span>
                        Votes
                      </h3>
                    </div>
                    <a href="/posts/{{ $post->id }}/votes" class="text-sm font-medium text-fp-accent hover:text-fp-accent">Manage</a>
                  </div>
                </div>
                <div class="border-t border-white/5">
                  <dl class="divide-y divide-white/5">
                    @foreach($post->votes as $vote)
                      <div class="px-4 py-4 ">
                        <p class="text-xs text-fp-mid">{{ $vote->negative ? 'Downvoted' : 'Upvoted' }} by <a class="hover:underline text-fp-hi font-medium " href="/contacts/{{ $vote->contact_id }}">{{ $vote->contact->name }}</a> {{ $vote->created_at->diffForHumans() }}</p>
                      </div>
                    @endforeach
                    @if($post->votes_count > 5)
                      <div class="px-4 py-4 ">
                        <p class="text-xs text-fp-mid">
                          <a class="hover:underline text-fp-hi font-medium " href="/posts/{{ $post->id }}/votes">View all votes</a>
                        </p>
                      </div>
                    @endif
                  </dl>
                </div>
              </div>
            </div>

            @if($otherPosts->isNotEmpty())
              <div class="mt-8">
                <div class="overflow">
                  <div class="px-4 py-4 sm:px-6">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">Merge</h3>
                    </div>
                  </div>
                  <div class="border-t border-white/5 px-4 py-4 sm:px-6">
                    <p class="text-sm text-fp-mid">Merge this post into another. Its votes and comments move over and this post is deleted.</p>
                    <form action="{{ route('posts.merge', $post->id) }}" method="POST" class="mt-3 space-y-3" onsubmit="return confirm('Merge this post into the selected one? This post will be deleted.')">
                      @csrf
                      <label for="target_post_id" class="sr-only">Merge into</label>
                      <select id="target_post_id" name="target_post_id" class="block w-full rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-fp-hi ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-fp-accent" required>
                        <option value="">Select a post…</option>
                        @foreach($otherPosts as $other)
                          <option value="{{ $other->id }}">{{ $other->title }}</option>
                        @endforeach
                      </select>
                      <button type="submit" class="rounded-md bg-fp-surface px-2.5 py-1.5 text-sm font-semibold text-fp-hi ring-1 ring-inset ring-white/10 hover:bg-fp-surface">Merge post</button>
                    </form>
                  </div>
                </div>
              </div>
            @endif
          </div>
        </div>
      </section>
    </div>
</x-app-layout>
