<?php

namespace App\Support;

use App\Models\Contact;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Contact metadata pack/unpack, extracted from the controller so both the HTTP
 * and MCP transports present contacts identically. Operates on plain arrays and
 * models, never on a Request.
 */
class ContactPresenter
{
    private const KNOWN_FIELDS = ['name', 'email', 'mrr', 'ltv', 'avatar', 'tags', 'metadata'];

    private const MAX_METADATA_KEYS = 50;

    private const MAX_METADATA_DEPTH = 3;

    private const MAX_METADATA_BYTES = 16384;

    /**
     * Enforce metadata limits before persistence: no reserved pilgrim_* keys, at
     * most 50 keys, nesting depth ≤ 3, and ≤ 16 KB encoded. Throws a validation
     * error keyed to `metadata` so both transports surface a clear message.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function guardMetadata(array $metadata): void
    {
        foreach (array_keys($metadata) as $key) {
            if (str_starts_with((string) $key, 'pilgrim_')) {
                self::rejectMetadata("The metadata key [{$key}] is reserved.");
            }
        }

        if (self::countKeys($metadata) > self::MAX_METADATA_KEYS) {
            self::rejectMetadata('Metadata may not exceed '.self::MAX_METADATA_KEYS.' keys.');
        }

        if (self::depth($metadata) > self::MAX_METADATA_DEPTH) {
            self::rejectMetadata('Metadata may not be nested deeper than '.self::MAX_METADATA_DEPTH.' levels.');
        }

        if (strlen((string) json_encode($metadata)) > self::MAX_METADATA_BYTES) {
            self::rejectMetadata('Metadata may not exceed 16 KB.');
        }
    }

    private static function rejectMetadata(string $message): void
    {
        throw ValidationException::withMessages(['metadata' => $message]);
    }

    /**
     * @param  array<mixed>  $array
     */
    private static function countKeys(array $array): int
    {
        $count = 0;

        foreach ($array as $value) {
            $count++;
            if (is_array($value)) {
                $count += self::countKeys($value);
            }
        }

        return $count;
    }

    /**
     * @param  array<mixed>  $array
     */
    private static function depth(array $array): int
    {
        $max = 1;

        foreach ($array as $value) {
            if (is_array($value)) {
                $max = max($max, 1 + self::depth($value));
            }
        }

        return $max;
    }

    /**
     * Custom fields become metadata: everything outside the known columns, merged
     * with an explicit `metadata` map.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function extractMetadata(array $input): array
    {
        $extras = Arr::except($input, self::KNOWN_FIELDS);

        $explicit = $input['metadata'] ?? [];
        if (! is_array($explicit)) {
            $explicit = [];
        }

        return array_merge($extras, $explicit);
    }

    /**
     * Encode metadata for persistence, preserving the contact's reserved pilgrim_* fields.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function encodeMetadata(array $metadata, ?Contact $contact = null): string
    {
        $base = [];

        if ($contact && $contact->metadata) {
            $existing = json_decode($contact->metadata, true) ?? [];
            $base = Arr::except($existing, Contact::PILGRIM_METADATA_FIELDS);
        }

        $merged = array_merge($base, $metadata);

        if ($contact && $contact->pilgrimFields) {
            $merged = array_merge($merged, $contact->pilgrimFields);
        }

        return json_encode($merged);
    }

    /**
     * Present a contact with the raw metadata key removed and custom fields flattened.
     *
     * @return array<string, mixed>
     */
    public static function present(Contact $contact): array
    {
        $contactArray = $contact->toArray();
        unset($contactArray['metadata']);

        return array_merge($contactArray, $contact->metadata_array ?? []);
    }
}
