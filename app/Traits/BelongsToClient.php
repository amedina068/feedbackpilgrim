<?php

namespace App\Traits;

trait BelongsToClient
{
    public function scopeClient($query, $clientId): void
    {
        $query->where('client_id', $clientId);
    }
}
