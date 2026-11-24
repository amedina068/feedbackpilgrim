<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $guarded = [];

    const STATUS_PENDING = 0;

    const STATUS_RESOLVED = 1;

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function resolved(): bool
    {
        return $this->status_id == self::STATUS_RESOLVED;
    }

    public function pending(): bool
    {
        return $this->status_id == self::STATUS_PENDING;
    }
}
