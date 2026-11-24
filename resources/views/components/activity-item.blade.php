<div class="px-4 py-4 ">
    <p class="text-xs text-fp-mid">
        <span class="font-medium text-fp-hi">{{ $activity->causer->name }} </span>
        @if($activity->description == 'vote_created')
            upvoted
        @elseif($activity->description == 'vote_deleted')
            unvoted
        @elseif($activity->description == 'comment_created')
            commented
        @elseif($activity->description == 'post_created')
            created
        @endif
        <a class="text-fp-hi font-medium hover:underline" href="{{ $activity->subject->path }}">{{ $activity->subject->title }} </a>
        <span class="text-fp-low">{{ $activity->created_at->diffForHumans() }}</span>
    </p>
</div>