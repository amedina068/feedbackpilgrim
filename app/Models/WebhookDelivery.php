<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    /**
     * Fixed result codes. The response_body column stores one of these codes.
     * It never stores the body of the remote response.
     */
    const RESULT_DELIVERED = 'delivered';

    const RESULT_HTTP_ERROR = 'http_error';

    const RESULT_REDIRECT_REFUSED = 'redirect_refused';

    const RESULT_CONNECTION_FAILED = 'connection_failed';

    const RESULT_ADDRESS_REFUSED = 'address_refused';

    protected $guarded = [];

    protected $casts = [
        'success' => 'boolean',
        'status_code' => 'integer',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
