<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use HasFactory;

    const EVENT_POST_CREATED = 'post.created';

    const EVENT_POST_STATUS_CHANGED = 'post.status_changed';

    const EVENT_POST_COMPLETED = 'post.completed';

    const EVENTS = [
        self::EVENT_POST_CREATED,
        self::EVENT_POST_STATUS_CHANGED,
        self::EVENT_POST_COMPLETED,
    ];

    protected $guarded = [];

    protected $hidden = ['secret'];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }
}
