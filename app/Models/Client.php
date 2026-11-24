<?php

namespace App\Models;

use App\Enums\SendingDomainStatus;
use App\Support\MailSender;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory;

    /**
     * Subdomains of APP_DOMAIN that a client can never use as its slug,
     * because the app or the infrastructure uses them.
     *
     * @var list<string>
     */
    public const RESERVED_SLUGS = [
        'admin', 'api', 'app', 'assets', 'blog', 'cdn', 'dashboard', 'dev', 'docs',
        'help', 'mail', 'mcp', 'smtp', 'staging', 'static', 'status', 'support', 'www',
    ];

    protected $guarded = [];

    protected $hidden = [
        'identity_secret',
    ];

    protected $casts = [
        'identity_secret' => 'encrypted',
        'require_identity_verification' => 'boolean',
    ];

    /**
     * New clients require identity verification. The column default stays
     * false so that clients made before this flag keep working.
     */
    protected $attributes = [
        'require_identity_verification' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            $client->publishable_key ??= static::generatePublishableKey();
            $client->identity_secret ??= static::generateIdentitySecret();
        });

        static::created(function (Client $client) {
            $client->settings()->create([
                'name' => ClientSetting::NAME_TESTIMONIAL_RATING,
                'value' => '0',
            ]);

            $client->settings()->create([
                'name' => ClientSetting::NAME_TESTIMONIAL_AUTOMATION,
                'value' => '0',
            ]);

            $client->settings()->create([
                'name' => ClientSetting::NAME_TESTIMONIAL_EMAIL_BODY,
                'value' => 'Hi {name},

We hope you\'re satisfied with the recent completion of your feature request! Your feedback means the world to us, and we\'d love to hear about your experience.

Would you mind taking a moment to share a testimonial about your experience with us? Your words help other customers understand the value we provide and help us continue to improve.

{testimonialButton}

This will only take a few minutes, and we\'re grateful for any feedback you can provide.

Thank you for being a valued customer!

---

If you have any questions or concerns, please don\'t hesitate to reach out to us.

This is an automated email, but we\'re always here to help if you need assistance.',
            ]);

            Stage::seedDefaultsFor($client);
            Board::seedDefaultFor($client);
        });
    }

    /**
     * The public key that the embed widget sends to identify the client.
     */
    public static function generatePublishableKey(): string
    {
        return 'pk_'.Str::random(32);
    }

    public static function generateIdentitySecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Forget the old value first. On save, Eloquent decrypts the old value to
     * compare it, and that fails when APP_KEY changed after the old save.
     */
    public function regenerateIdentitySecret(): void
    {
        $this->forceFill(['identity_secret' => null])->syncOriginalAttribute('identity_secret');

        $this->forceFill(['identity_secret' => static::generateIdentitySecret()])->save();
    }

    /**
     * The identity secret, or null when the current APP_KEY cannot decrypt it.
     */
    public function readableIdentitySecret(): ?string
    {
        try {
            return $this->identity_secret;
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * The HMAC that the host app sends as user_hash for this email.
     */
    public function identityHashFor(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), (string) $this->identity_secret);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * True when every user of this client is deactivated. The public site, the
     * embeds and the widget of such a client stop. A client with one active
     * user, or with no user, keeps them.
     */
    public function hasOnlyDeactivatedUsers(): bool
    {
        $counts = $this->users()->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when users.deactivated_at is null then 1 else 0 end) as active')
            ->first();

        return (int) $counts->total > 0 && (int) $counts->active === 0;
    }

    public function settings(): HasMany
    {
        return $this->hasMany(ClientSetting::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function sendingDomain(): HasOne
    {
        return $this->hasOne(SendingDomain::class);
    }

    /**
     * Decide the sender for emails to this client's contacts.
     * Only a verified sending domain sends through SES from the client address.
     * The name loses its control characters, so it cannot add a mail header.
     */
    public function mailSender(): MailSender
    {
        $sendingDomain = $this->sendingDomain;
        $name = (string) preg_replace('/\p{Cc}/u', '', (string) $this->name);

        if ($sendingDomain?->status === SendingDomainStatus::Verified) {
            return new MailSender('ses', "{$sendingDomain->sender_local_part}@{$sendingDomain->domain}", $name);
        }

        return new MailSender(null, config('mail.from.address'), $name);
    }

    public function getPublicSiteAttribute()
    {
        $appUrl = (string) config('app.url');
        $port = parse_url($appUrl, PHP_URL_PORT);
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';

        return "{$scheme}://{$this->slug}.".config('app.domain').($port ? ":{$port}" : '');
    }

    public function getReleaseNotesIframeUrlAttribute(): string
    {
        return "{$this->publicSite}/iframe/release-notes";
    }

    public function getTestimonialsIframeUrlAttribute(): string
    {
        return "{$this->publicSite}/iframe/testimonials";
    }

    public function getRoadmapIframeUrlAttribute(): string
    {
        return "{$this->publicSite}/iframe/roadmap";
    }

    public function getTestimonialRatingEnabledAttribute(): bool
    {
        $setting = $this->settings()->testimonialRating()->first();

        return $setting ? (bool) $setting->value : false;
    }

    public function getTestimonialAutomationEnabledAttribute(): bool
    {
        $setting = $this->settings()->testimonialAutomation()->first();

        return $setting ? (bool) $setting->value : false;
    }

    public function getTestimonialEmailBodyAttribute(): string
    {
        $setting = $this->settings()->testimonialEmailBody()->first();

        return $setting ? $setting->value : '';
    }

    public function getSlackWebhookUrlAttribute(): ?string
    {
        return $this->settings()->slackWebhook()->value('value');
    }
}
