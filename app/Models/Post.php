<?php

namespace App\Models;

use App\Support\SlackNotifier;
use App\Support\WebhookDispatcher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    const STATUS_COMPLETED = 'completed';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_PENDING = 'pending';

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
            ]);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function scopeForBoard($query, $boardId): void
    {
        $query->when($boardId, fn ($query) => $query->where('board_id', $boardId));
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(Vote::class, 'votable');
    }

    public function pending(): bool
    {
        return $this->status == self::STATUS_PENDING;
    }

    public function inProgress(): bool
    {
        return $this->status == self::STATUS_IN_PROGRESS;
    }

    public function completed(): bool
    {
        return $this->status == self::STATUS_COMPLETED;
    }

    /**
     * The label of the status badge on the public pages.
     *
     * A post in a planned, an active or a complete stage shows the name of
     * that stage. A post in an open or a closed stage shows no badge. A post
     * with no stage uses its legacy status. The legacy status is
     * "in_progress" for a planned stage and for an active stage, so it cannot
     * tell the two stages apart.
     */
    public function publicStatusLabel(): ?string
    {
        if ($this->stage) {
            return in_array($this->stage->type, [Stage::TYPE_PLANNED, Stage::TYPE_ACTIVE, Stage::TYPE_COMPLETE], true)
                ? $this->stage->name
                : null;
        }

        return match ($this->status) {
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Shipped',
            default => null,
        };
    }

    /**
     * Tells if the public status badge uses the color of a finished post.
     */
    public function publicStatusIsComplete(): bool
    {
        return $this->stage ? $this->stage->isComplete() : $this->completed();
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if ($post->isDirty('stage_id') && $post->stage_id) {
                $type = Stage::query()->whereKey($post->stage_id)->value('type');

                if ($type) {
                    $post->status = Stage::legacyStatusForType($type);
                }
            } elseif ($post->isDirty('status') && $post->status && $post->client_id) {
                $stageId = Stage::firstForLegacyStatus($post->client_id, $post->status)?->id;

                if ($stageId) {
                    $post->stage_id = $stageId;
                }
            }
        });

        // A post without a board goes to the first public board. If the client has no public board, it goes to the first board.
        static::creating(function (Post $post) {
            if (! $post->board_id && $post->client_id) {
                $post->board_id = Board::query()
                    ->where('client_id', $post->client_id)
                    ->orderByDesc('is_public')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->value('id');
            }
        });

        static::created(function (Post $post) {
            WebhookDispatcher::dispatch($post->client_id, Webhook::EVENT_POST_CREATED, $post->toWebhookPayload());

            SlackNotifier::notify($post->client_id, '🆕 New feedback: *'.SlackNotifier::escape($post->title)."*\n{$post->path}");
        });
    }

    public function scopeVotedByContact($query, $contactId): void
    {
        $query->addSelect([
            'contact_vote' => Vote::query()
                ->where('contact_id', $contactId)
                ->where('votable_type', self::class)
                ->whereColumn('votes.votable_id', 'posts.id')
                ->select('votes.id')
                ->limit(1),
        ]);
    }

    /**
     * Posts that the public site, the iframes and contacts can see: published
     * posts on a public board. A post on a private board is for the team only.
     */
    public function scopePublic($query): void
    {
        $query->whereNotNull('posts.published_at')
            ->whereIn('posts.board_id', Board::query()->select('id')->where('is_public', true));
    }

    /**
     * Priority score = number of votes weighted by each voter's MRR.
     * score = COUNT(votes) + SUM(voter.mrr). Selected as a `score` column so it is sortable.
     */
    public function scopeWithScore($query): void
    {
        $query->addSelect([
            'score' => Vote::query()
                ->selectRaw('COALESCE(COUNT(*), 0) + COALESCE(SUM(contacts.mrr), 0)')
                ->leftJoin('contacts', 'contacts.id', '=', 'votes.contact_id')
                ->where('votes.votable_type', self::class)
                ->whereColumn('votes.votable_id', 'posts.id'),
        ]);
    }

    public function scopeStageName($query): void
    {
        $query->addSelect([
            'stage_name' => Stage::query()
                ->select('name')
                ->whereColumn('posts.stage_id', 'stages.id')
                ->limit(1),
        ]);
    }

    public function getContentExcerptAttribute()
    {
        return Str::limit($this->content, 200);
    }

    public function getPathAttribute(): string
    {
        return url('/posts/'.$this->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebhookPayload(): array
    {
        $this->loadMissing('stage');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status,
            'board_id' => $this->board_id,
            'contact_id' => $this->contact_id,
            'stage' => $this->stage ? [
                'id' => $this->stage->id,
                'name' => $this->stage->name,
                'type' => $this->stage->type,
            ] : null,
        ];
    }
}
