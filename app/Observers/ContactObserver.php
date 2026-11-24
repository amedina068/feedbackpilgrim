<?php

namespace App\Observers;

use App\Jobs\SyncContactAttributes;
use App\Models\Contact;

class ContactObserver
{
    /**
     * Handle the Contact "created" event.
     */
    public function created(Contact $contact): void
    {
        if ($contact->metadata) {
            SyncContactAttributes::dispatch($contact);
        }
    }

    /**
     * Handle the Contact "updated" event.
     */
    public function updated(Contact $contact): void
    {
        if ($contact->isDirty('metadata')) {
            SyncContactAttributes::dispatch($contact);
        }
    }
}
