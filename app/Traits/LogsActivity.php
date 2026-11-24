<?php

namespace App\Traits;

use Spatie\Activitylog\Models\Activity;

trait LogsActivity
{
    public function logActivity($clientId, $causer, $subject, $log)
    {
        Activity::create([
            'client_id' => $clientId,
            'log_name' => 'default',
            'description' => $log,
            'causer_id' => $causer->id,
            'causer_type' => get_class($causer),
            'subject_id' => $subject->id,
            'subject_type' => get_class($subject),
        ]);
    }
}
