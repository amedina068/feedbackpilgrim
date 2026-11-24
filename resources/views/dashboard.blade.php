<x-app-layout>
<div class="sm:px-6 lg:px-8">
  <div class="flex items-baseline justify-between border-b border-white/5 pb-4">
    <h1 class="px-4 sm:px-0 text-2xl font-semibold tracking-tight text-fp-hi">Dashboard</h1>
  </div>
  <section class="pb-24 pt-6">
      <div>
          <!-- Dashboard Summary -->
          <div class="mb-8">
            <dashboard-summary :summary="{{ json_encode($dashboardSummary) }}"></dashboard-summary>
          </div>

          <!-- Charts and Top Lists Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Contact Activity Chart -->
            <div>
              <dashboard-chart :data="{{ json_encode($loginsByDay) }}"></dashboard-chart>
            </div>

            <!-- Top Active Contacts -->
            <div>
              <top-contacts-list
                :contacts="{{ json_encode($topContactsByLogins) }}"
                title="Most Active Contacts"
                type="logins">
              </top-contacts-list>
            </div>
          </div>

          <!-- Top Lists Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Top Post Publishers -->
            <div>
              <top-contacts-list
                :contacts="{{ json_encode($topContactsByPosts) }}"
                title="Top Post Publishers"
                type="posts">
              </top-contacts-list>
            </div>

            <!-- Most Engaged Contacts -->
            <div>
              <top-contacts-list
                :contacts="{{ json_encode($topContactsByActivity) }}"
                title="Most Engaged Contacts"
                type="activity">
              </top-contacts-list>
            </div>
          </div>

          <div>
            <div>
              <div class="overflow-hidden">
                <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Latest Activity
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t border-white/5">
                  <dl class="divide-y divide-white/5">
                    @forelse($latestActivities as $activity)
                      <x-activity-item :activity="$activity" />
                    @empty
                      <p class="text-center py-4 text-sm text-fp-mid">No activity yet</p>
                    @endforelse
                  </dl>
                </div>
              </div>
            </div>
            @if(! $latestContacts->count())
              <div class="mb-8 mt-8">
                <div class="overflow-hidden">
                  <div class="border-b border-white/5 px-4 py-5 sm:px-6">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="text-fp-warning w-6 h-6">
                        <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd" />
                      </svg>

                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Get Started
                      </h3>
                    </div>
                  </div>
                  <div class="px-4 py-5 sm:p-6 text-sm text-fp-mid">
                    <div class="mb-2">Add the following snippet to the head of your app. Get the identity secret for <span class="font-mono">userHash</span> in <a href="{{ route('settings.widget') }}" class="font-medium text-fp-accent">Settings &rarr; Embed Widget</a>.</div>
                    <div>
                      <pre class="-mt-6 -mb-12">
                        <code ref="code" class="language-xml rounded-md">{{ '<script src="'.url('/pilgrim.js').'" data-client="'.$key.'"></script>' }}</code>
                      </pre>
                    </div>
                    <div>
                      <div class="mt-6 mb-2">Add this snippet at the bottom of your <span class="ml-1 inline-block text-sm font-mono bg-fp-elevated text-fp-accent px-2 py-.5 rounded-xs">{{ '<body>' }}</span> tag.</div>
                      <pre class="-mt-6 -mb-12">
                        <code ref="code" class="language-xml rounded-md">{{ '<script>
  pilgrim({
    releaseNotes: {
      show: true, //will provide a link to open the release notes modal
      open: true, // will automatically open the release notes modal
    },
    contact: {
      email: "jane@example.com", // required,
      name: "Jane Doe", // optional
      ltv: 199.99, // optional
      mrr: 19.99, // optional
      /*
      | you can add any other fields you want
      | these will be created as custom fields
      */
      member_since: "2024-01-01 13:00",
      membership_type: "lifetime",
    },
    userHash: "HASH_FROM_YOUR_SERVER", // required when identity verification is on
  })
</script>' }}</code>
                      </pre>
                    </div>
                  </div>
                </div>

              </div>
              @endif
              <div>
                <div class="overflow-hidden">
                <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Latest Posts
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t border-white/5">
                  <ul class="divide-y divide-white/5">
                    @forelse($latestPosts as $post)
                      <li class="relative gap-x-6">
                        <a href="/posts/{{ $post->id }}" class="block p-4 hover:bg-fp-surface sm:px-6">
                          <div class="md:flex md:items-center md:justify-between">
                            <div>
                              <div class="flex justify-between text-sm items-center">
                                <div class="text-sm text-fp-mid">
                                  <span class="font-mono text-xs text-fp-low">{{ $post->created_at->diffForHumans() }}</span>
                                  @if($post->contact_id)
                                    <span> by {{ $post->contact->name }}</span>
                                    @if($post->contact->top_customer)
                                    <span class="ml-2 inline-flex items-center rounded-md bg-fp-success/10 px-2 py-1 text-xs font-medium text-fp-success ring-1 ring-inset ring-fp-success/20">Top Customer</span>
                                    @endif
                                  @endif
                                </div>
                              </div>
                              <div>
                                <div class="mt-2 md:flex md:items-center md:space-x-2">
                                  <h3 class="text-base text-fp-hi font-medium">
                                    {{$post->title }}
                                  </h3>
                                  @if($post->inProgress())
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full px-1.5 py-0.5 text-xs font-medium text-fp-warning">
                                      <svg class="h-1.5 w-1.5 fill-fp-warning" viewBox="0 0 6 6" aria-hidden="true">
                                        <circle cx="3" cy="3" r="3" />
                                      </svg>
                                      In Progress
                                    </span>
                                  @endif
                                  @if($post->completed())
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full px-1.5 py-0.5 text-xs font-medium text-fp-success">
                                      <svg class="h-1.5 w-1.5 fill-fp-success" viewBox="0 0 6 6" aria-hidden="true">
                                        <circle cx="3" cy="3" r="3" />
                                      </svg>
                                      Completed
                                    </span>
                                  @endif
                                  @if($post->archived_at)
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full px-1.5 py-0.5 text-xs font-medium text-fp-low">
                                      <svg class="h-1.5 w-1.5 fill-fp-low" viewBox="0 0 6 6" aria-hidden="true">
                                        <circle cx="3" cy="3" r="3" />
                                      </svg>
                                      Archived
                                    </span>
                                  @endif
                                </div>
                              </div>
                              @if($post->tags->count())
                                <div class="mt-4 flex flex-wrap">
                                  @foreach($post->tags as $tag)
                                    <span class="mb-2 mr-2 inline-flex items-center rounded-md bg-white/5 px-2 py-1 text-xs font-medium text-fp-mid ring-1 ring-inset ring-white/10">{{ $tag->title }}</span>
                                  @endforeach
                                </div>
                              @endif
                            </div>
                            <div class="flex space-x-4 items-center mt-2 md:mt-0">
                              <div class="inline-flex items-center space-x-2">
                                <span class="font-mono text-sm text-fp-mid">{{ $post->votes_count }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" />
                                </svg>
                              </div>
                              <div class="inline-flex items-center space-x-2">
                                <span class="font-mono text-sm text-fp-mid">{{ $post->comments_count }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                </svg>
                              </div>
                            </div>
                          </div>
                        </a>
                      </li>
                    @empty
                      <li class="px-4 pt-6 pb-8">
                        <x-empty-state title="No Posts" description="Get started by adding a post." href="/posts/create" action="Add posts" />
                      </li>
                    @endforelse
                  </ul>
                </div>
              </div>
              </div>
              <div class="mt-8">
                <div class="overflow-hidden">
                <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Latest Contacts
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t border-white/5">
                  <ul class="divide-y divide-white/5">
                    @forelse($latestContacts as $contact)
                      <x-contact-row :contact="$contact" />
                    @empty
                      <li class="px-4 pt-6 pb-8">
                        <x-empty-state title="No Contacts yet" />
                      </li>
                    @endforelse
                  </ul>
                </div>
              </div>
              </div>
              <div class="mt-8">
                <div class="overflow-hidden">
                <div class="px-4 py-4 sm:px-6">
                  <div class="flex justify-between items-baseline">
                    <div class="flex space-x-2 items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.25" stroke="currentColor" class="w-6 text-fp-low h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                      </svg>
                      <h3 class="text-lg font-semibold leading-7 text-fp-hi">
                        Latest Comments
                      </h3>
                    </div>
                  </div>
                </div>
                <div class="border-t border-white/5">
                  <dl class="divide-y divide-white/5">
                    @forelse($latestComments as $comment)
                      <div class="px-4 sm:px-6 py-4 ">
                        <p class="text-xs text-fp-low">
                          <a class="hover:underline text-fp-mid font-medium " href="/contacts/{{ $comment->contact_id }}">{{ $comment->contact->email }}</a> commented {{ $comment->created_at->diffForHumans() }}
                        </p>
                        <p class="mt-2 text-sm text-fp-mid">
                          {{ Str::limit($comment->content, 100) }}
                        </p>
                      </div>
                    @empty
                      <h3 class="text-center py-12 text-sm font-semibold text-fp-hi">No Comments yet</h3>
                    @endforelse
                  </dl>
                </div>
              </div>
              </div>
          </div>
      </div>
  </section>
</div>
</x-app-layout>
