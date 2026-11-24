<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    const NAME_DOMAIN = 'domain';

    const NAME_TESTIMONIAL_RATING = 'testimonial_rating';

    const NAME_TESTIMONIAL_AUTOMATION = 'testimonial_automation';

    const NAME_TESTIMONIAL_EMAIL_BODY = 'testimonial_email_body';

    const NAME_SLACK_WEBHOOK_URL = 'slack_webhook_url';

    public function scopeSlackWebhook($query): void
    {
        $query->where('name', self::NAME_SLACK_WEBHOOK_URL)->whereNotNull('value');
    }

    public function scopeDomain($query): void
    {
        $query->where('name', self::NAME_DOMAIN)->whereNotNull('value');
    }

    public function scopeTestimonialRating($query): void
    {
        $query->where('name', self::NAME_TESTIMONIAL_RATING);
    }

    public function scopeTestimonialAutomation($query): void
    {
        $query->where('name', self::NAME_TESTIMONIAL_AUTOMATION);
    }

    public function scopeTestimonialEmailBody($query): void
    {
        $query->where('name', self::NAME_TESTIMONIAL_EMAIL_BODY);
    }
}
