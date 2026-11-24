<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Stage extends Model
{
    use HasFactory;

    const TYPE_OPEN = 'open';

    const TYPE_PLANNED = 'planned';

    const TYPE_ACTIVE = 'active';

    const TYPE_COMPLETE = 'complete';

    const TYPE_CLOSED = 'closed';

    const TYPES = [
        self::TYPE_OPEN,
        self::TYPE_PLANNED,
        self::TYPE_ACTIVE,
        self::TYPE_COMPLETE,
        self::TYPE_CLOSED,
    ];

    protected $guarded = [];

    protected $casts = [
        'show_on_roadmap' => 'boolean',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    public function scopeOnRoadmap(Builder $query): void
    {
        $query->where('show_on_roadmap', true)->orderBy('position');
    }

    public function isComplete(): bool
    {
        return $this->type === self::TYPE_COMPLETE;
    }

    /**
     * Canonical default status pipeline seeded for every new client.
     *
     * @return array<int, array{name: string, slug: string, color: string, type: string, show_on_roadmap: bool}>
     */
    public static function defaultDefinitions(): array
    {
        return [
            ['name' => 'Open', 'slug' => 'open', 'color' => '#94a3b8', 'type' => self::TYPE_OPEN, 'show_on_roadmap' => false],
            ['name' => 'Under Review', 'slug' => 'under-review', 'color' => '#eab308', 'type' => self::TYPE_OPEN, 'show_on_roadmap' => false],
            ['name' => 'Planned', 'slug' => 'planned', 'color' => '#6366f1', 'type' => self::TYPE_PLANNED, 'show_on_roadmap' => true],
            ['name' => 'In Progress', 'slug' => 'in-progress', 'color' => '#3b82f6', 'type' => self::TYPE_ACTIVE, 'show_on_roadmap' => true],
            ['name' => 'Complete', 'slug' => 'complete', 'color' => '#22c55e', 'type' => self::TYPE_COMPLETE, 'show_on_roadmap' => true],
            ['name' => 'Closed', 'slug' => 'closed', 'color' => '#6b7280', 'type' => self::TYPE_CLOSED, 'show_on_roadmap' => false],
        ];
    }

    /**
     * Seed the default status pipeline for a client, skipping any slugs that already exist.
     */
    public static function seedDefaultsFor(Client $client): void
    {
        $existing = static::query()->where('client_id', $client->id)->pluck('slug')->filter()->all();
        $position = (int) static::query()->where('client_id', $client->id)->max('position');

        foreach (static::defaultDefinitions() as $definition) {
            if (in_array($definition['slug'], $existing, true)) {
                continue;
            }

            static::query()->create(array_merge($definition, [
                'client_id' => $client->id,
                'position' => ++$position,
            ]));
        }
    }

    /**
     * The first stage of a client that matches a legacy Post status.
     */
    public static function firstForLegacyStatus(int $clientId, string $status): ?self
    {
        return static::query()
            ->where('client_id', $clientId)
            ->ofType(static::typeForLegacyStatus($status))
            ->orderBy('position')
            ->first();
    }

    /**
     * Map a legacy Post status enum value to the matching default stage type.
     */
    public static function typeForLegacyStatus(?string $status): string
    {
        return match ($status) {
            'in_progress' => self::TYPE_ACTIVE,
            'completed' => self::TYPE_COMPLETE,
            default => self::TYPE_OPEN,
        };
    }

    /**
     * Map a stage type back to the legacy Post status enum kept as a derived mirror.
     */
    public static function legacyStatusForType(?string $type): string
    {
        return match ($type) {
            self::TYPE_PLANNED, self::TYPE_ACTIVE => 'in_progress',
            self::TYPE_COMPLETE => 'completed',
            default => 'pending',
        };
    }

    protected static function booted(): void
    {
        static::creating(function (Stage $stage) {
            if (empty($stage->slug) && ! empty($stage->name)) {
                $stage->slug = Str::slug($stage->name);
            }
        });
    }
}
