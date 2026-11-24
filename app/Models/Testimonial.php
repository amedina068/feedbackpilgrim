<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use LasseRafn\InitialAvatarGenerator\InitialAvatar;

class Testimonial extends Model
{
    use HasFactory;

    public const RATING_MIN = 1;

    public const RATING_MAX = 5;

    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(Vote::class, 'votable');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }

    /**
     * The testimonials that a contact can see on the public site: the approved
     * testimonials and the own testimonials of the contact.
     */
    public function scopeVisibleTo(Builder $query, Contact $contact): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereNotNull('approved_at')
            ->orWhere('contact_id', $contact->id));
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('approved_at');
    }

    public function getIsApprovedAttribute(): bool
    {
        return ! is_null($this->approved_at);
    }

    public function getAvatarImageAttribute($value): string
    {
        if ($value) {
            return $value;
        }

        if ($this->contact) {
            return $this->contact->avatar;
        }

        return $this->generateInitialsImage();
    }

    public function getDisplayUsernameAttribute(): string
    {
        if ($this->contact) {
            return $this->contact->name ?? $this->contact->email;
        }

        return $this->username;
    }

    public function approve(): void
    {
        $this->update(['approved_at' => now()]);
    }

    public function decline(): void
    {
        $this->update(['approved_at' => null]);
    }

    private function generateInitialsImage(): string
    {
        $name = $this->contact ? ($this->contact->name ?? $this->contact->email) : $this->username;
        $avatar = (new InitialAvatar)->name($name)->generate()->stream('png');
        Storage::disk('public')->put("avatars/testimonials/{$this->id}/avatar.png", $avatar);

        return Storage::disk('public')->url("/avatars/testimonials/{$this->id}/avatar.png");
    }
}
