<?php

namespace App\Support;

use App\Models\Contact;

/**
 * Result of a contact upsert: the contact plus whether it was newly created
 * (drives the 201 vs 200 status on the HTTP transport and wasCreated on MCP).
 */
readonly class ContactUpsertResult
{
    public function __construct(
        public Contact $contact,
        public bool $wasCreated,
    ) {}
}
