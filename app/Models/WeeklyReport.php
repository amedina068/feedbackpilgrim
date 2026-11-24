<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'week_start',
        'week_end',
        'metrics',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'metrics' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
