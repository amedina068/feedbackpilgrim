<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Board extends Model
{
    use HasFactory;

    const DEFAULT_NAME = 'Feature Requests';

    const DEFAULT_SLUG = 'feature-requests';

    protected $guarded = [];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    /**
     * Seed (or return) the default board for a client.
     */
    public static function seedDefaultFor(Client $client): self
    {
        return static::query()->firstOrCreate(
            ['client_id' => $client->id, 'slug' => self::DEFAULT_SLUG],
            ['name' => self::DEFAULT_NAME, 'position' => 1, 'is_public' => true],
        );
    }

    protected static function booted(): void
    {
        static::creating(function (Board $board) {
            if (empty($board->slug) && ! empty($board->name)) {
                $board->slug = Str::slug($board->name);
            }
        });
    }
}
