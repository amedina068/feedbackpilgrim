<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    const ONBOARDING_CLIENT = 'client';

    const ONBOARDING_COMPLETED = 'completed';

    /** The login form and a closed session show this text to a deactivated user. */
    const DEACTIVATED_MESSAGE = 'This account is deactivated. Ask the owner of this instance to reactivate it.';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_owner' => 'boolean',
        'deactivated_at' => 'datetime',
    ];

    /** A deactivated user keeps all data and cannot authenticate. */
    public function isDeactivated(): bool
    {
        return ! is_null($this->deactivated_at);
    }

    public function deactivate(): void
    {
        $this->forceFill(['deactivated_at' => now()])->save();
    }

    public function reactivate(): void
    {
        $this->forceFill(['deactivated_at' => null])->save();
    }

    /**
     * The owner runs the instance and is the only user that opens the admin area.
     */
    public function isOwner(): bool
    {
        return (bool) $this->is_owner;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class);
    }

    public function hasAccess(): bool
    {
        return $this->onboarding == User::ONBOARDING_COMPLETED;
    }

    public function onboardingStep(): string
    {
        return match ($this->onboarding) {
            self::ONBOARDING_COMPLETED => '/dashboard',
            self::ONBOARDING_CLIENT => '/onboarding/project',
        };
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function contact(): HasOne
    {
        return $this->hasOne(Contact::class);
    }

    /**
     * The admin contact is the identity of this user on the public site and
     * in the MCP tools of one client. Create it when it does not exist.
     */
    public function adminContactFor(Client|int $client): Contact
    {
        return Contact::query()->firstOrCreate(
            [
                'user_id' => $this->id,
                'client_id' => $client instanceof Client ? $client->id : $client,
            ],
            [
                'name' => $this->name,
                'email' => $this->email,
                'metadata' => json_encode(['role' => 'admin']),
            ],
        );
    }
}
