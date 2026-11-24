<?php

namespace App\Models;

use Carbon\Carbon;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LasseRafn\InitialAvatarGenerator\InitialAvatar;

class Contact extends Authenticatable
{
    use HasFactory, Notifiable;

    public const AUTH_LINK_LIFETIME_HOURS = 24;

    public const EMAIL_AUTH_LINK_LIFETIME_DAYS = 7;

    const PILGRIM_METADATA_FIELDS = [
        'pilgrim_last_login',
        'pilgrim_last_release_note',
    ];

    protected $guarded = [];

    protected $hidden = [
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (Contact $contact) {
            $contact->uuid = (string) Str::uuid();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function getPilgrimField($pilgrimField, $default = null)
    {
        return Arr::get($this->pilgrimFields, $pilgrimField, $default);
    }

    public function setPilgrimField($pilgrimField, $value)
    {
        $pilgrimFields = $this->pilgrimFields;

        $pilgrimFields[$pilgrimField] = $value;

        $this->update([
            'metadata' => json_encode([
                ...$this->metadataArray,
                ...$pilgrimFields,
            ]),
        ]);
    }

    public function getLastLoginAttribute(): ?DateTime
    {
        $dateTime = $this->getPilgrimField('pilgrim_last_login');

        if (! $dateTime) {
            return null;
        }

        return new Carbon($dateTime);
    }

    public function getMetadataArrayAttribute(): array
    {
        if (! $this->metadata) {
            return [];
        }

        $metadata = $this->metadata;

        if (! is_array($metadata)) {
            $metadata = json_decode($this->metadata, true);
        }

        unset($metadata['pilgrim_last_login']);
        unset($metadata['pilgrim_last_release_note']);

        return $metadata;
    }

    public function getPilgrimFieldsAttribute()
    {
        $metadata = $this->metadata;

        if (! is_array($metadata)) {
            $metadata = json_decode($this->metadata, true);
        }

        return collect($metadata)
            ->filter(fn ($value, $key) => in_array($key, self::PILGRIM_METADATA_FIELDS))
            ->toArray();
    }

    public function getAvatarAttribute($value)
    {
        if (! $value) {
            return $this->generateInitialsImage();
        }

        return $value;
    }

    /**
     * An admin contact acts as its user on the public site. When the owner
     * deactivates that user, the contact cannot sign in either.
     */
    public function actsForDeactivatedUser(): bool
    {
        return $this->user_id !== null
            && User::query()->whereKey($this->user_id)->whereNotNull('deactivated_at')->exists();
    }

    /**
     * A signed sign-in link on the public site of the client. A link for a
     * page view (the widget, the admin) expires after AUTH_LINK_LIFETIME_HOURS.
     * The redirect must be a path on the public site.
     */
    public function generateAuthUrl(?string $redirect = null, ?DateTimeInterface $expiresAt = null): string
    {
        $path = URL::temporarySignedRoute('authenticate-contact', $expiresAt ?? now()->addHours(self::AUTH_LINK_LIFETIME_HOURS), [
            'contactUuid' => $this->uuid,
            'redirect_url' => $redirect,
        ], absolute: false);

        return $this->client->public_site.$path;
    }

    /**
     * A signed sign-in link for an email. It expires after EMAIL_AUTH_LINK_LIFETIME_DAYS.
     */
    public function generateEmailAuthUrl(?string $redirect = null): string
    {
        return $this->generateAuthUrl($redirect, now()->addDays(self::EMAIL_AUTH_LINK_LIFETIME_DAYS));
    }

    private function generateInitialsImage()
    {
        $avatar = (new InitialAvatar)->name($this->name ?? $this->email)->generate()->stream('png');
        Storage::disk('public')->put("avatars/{$this->id}/avatar.png", $avatar);

        return Storage::disk('public')->url("/avatars/{$this->id}/avatar.png");
    }
}
