<?php

namespace App\Models;

use App\Traits\BelongsToClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class ReleaseNote extends Model
{
    use BelongsToClient, HasFactory;

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public static function booted()
    {
        static::creating(function ($releaseNote) {
            $releaseNote->slug = Str::slug($releaseNote->title.' '.now()->format('Ymd'));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(Vote::class, 'votable');
    }

    public function scopeVotedByContact($query, $contactId): void
    {
        $query->addSelect([
            'contact_vote' => Vote::query()
                ->where('contact_id', $contactId)
                ->where('votable_type', self::class)
                ->whereColumn('votes.votable_id', 'release_notes.id')
                ->select('votes.id')
                ->limit(1),
        ]);
    }

    public function getPathAttribute()
    {
        return url("/release-notes/{$this->id}");
    }

    public function scopePublished($query): void
    {
        $query->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Publish the note. A note that already has a publication date keeps it.
     */
    public function publish(): bool
    {
        return $this->forceFill(['published_at' => $this->published_at ?? now()])->save();
    }

    public function unpublish(): bool
    {
        return $this->forceFill(['published_at' => null])->save();
    }
}
