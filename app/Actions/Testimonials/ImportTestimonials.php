<?php

namespace App\Actions\Testimonials;

use App\Jobs\CopyTestimonialAvatar;
use App\Models\Client;
use App\Models\Testimonial;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Imports testimonials exported from Senja (https://senja.io) as CSV.
 *
 * The Senja export ships a fixed set of columns; only text testimonials carry an
 * importable body, so rows without any testimonial text are skipped. Imported
 * testimonials are marked approved because a Senja export only contains
 * testimonials the user already curated.
 *
 * A row is skipped when a text is longer than the admin and public forms
 * accept. After the import, a queued job copies each https avatar to the
 * public disk. The testimonial has no avatar until the copy is done.
 *
 * A row is a duplicate when the client already has a testimonial with the
 * same user name and the same text. The Senja export has no ID column, so
 * these two values are the key. A duplicate row is not imported again.
 *
 * @phpstan-type ImportResult array{imported: int, skipped: int, duplicates: int}
 */
class ImportTestimonials
{
    /**
     * Columns that must be present for a file to be recognised as a Senja
     * export. A file missing all of these is almost certainly the wrong export.
     */
    private const REQUIRED_COLUMNS = ['text', 'customer_name'];

    /**
     * Upper bound on rows processed in one import. The whole file is written in
     * a single request-thread transaction, so this caps how long that
     * transaction (and its row locks) can stay open.
     */
    private const MAX_ROWS = 5000;

    /**
     * The longest testimonial text that the public and API forms accept.
     */
    private const MAX_DESCRIPTION_LENGTH = 5000;

    /**
     * The length of the string columns: title, username, subtitle and avatar_image.
     */
    private const MAX_STRING_LENGTH = 255;

    /**
     * How long, in seconds, the import lock of a client stays held.
     */
    private const LOCK_SECONDS = 120;

    /**
     * @return ImportResult
     */
    public function __invoke(string $path, Client $client): array
    {
        $result = Cache::lock("testimonial-import:{$client->id}", self::LOCK_SECONDS)
            ->get(fn (): array => $this->import($path, $client));

        if ($result === false) {
            throw ValidationException::withMessages([
                'file' => 'Another import of testimonials is running. Try again in a minute.',
            ]);
        }

        return $result;
    }

    /**
     * @return ImportResult
     */
    private function import(string $path, Client $client): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open testimonial import file: {$path}");
        }

        try {
            $header = $this->readRow($handle);

            if ($header === false) {
                throw ValidationException::withMessages([
                    'file' => 'The file is empty.',
                ]);
            }

            $columns = array_flip(array_map('trim', $header));

            $this->assertSenjaColumns($columns);

            $keys = $this->existingKeys($client);
            $imported = 0;
            $skipped = 0;
            $duplicates = 0;
            $avatars = [];

            DB::transaction(function () use ($handle, $columns, $client, &$keys, &$imported, &$skipped, &$duplicates, &$avatars): void {
                while (($row = $this->readRow($handle)) !== false) {
                    if ($imported + $skipped + $duplicates >= self::MAX_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => 'This file has more than '.self::MAX_ROWS.' rows. Split it into smaller files and import them one at a time.',
                        ]);
                    }

                    $attributes = $this->mapRow($row, $columns, $client);

                    if ($attributes === null) {
                        $skipped++;

                        continue;
                    }

                    $key = $this->key($attributes['username'], $attributes['description']);

                    if (isset($keys[$key])) {
                        $duplicates++;

                        continue;
                    }

                    $avatar = Arr::pull($attributes, 'avatar_url');
                    $testimonial = Testimonial::create($attributes);
                    $keys[$key] = true;
                    $imported++;

                    if ($avatar !== null) {
                        $avatars[] = [$testimonial, $avatar];
                    }
                }
            });

            foreach ($avatars as [$testimonial, $avatar]) {
                CopyTestimonialAvatar::dispatch($testimonial, $avatar);
            }

            return ['imported' => $imported, 'skipped' => $skipped, 'duplicates' => $duplicates];
        } finally {
            fclose($handle);
        }
    }

    /**
     * The keys of the testimonials that the client already has.
     *
     * @return array<string, true>
     */
    private function existingKeys(Client $client): array
    {
        return Testimonial::query()
            ->where('client_id', $client->id)
            ->select(['id', 'username', 'description'])
            ->lazyById()
            ->mapWithKeys(fn (Testimonial $testimonial): array => [
                $this->key((string) $testimonial->username, (string) $testimonial->description) => true,
            ])
            ->all();
    }

    private function key(string $username, string $description): string
    {
        return hash('sha256', $username."\0".$description);
    }

    /**
     * @param  resource  $handle
     * @return array<int, string|null>|false
     */
    private function readRow($handle): array|false
    {
        return fgetcsv($handle, null, ',', '"', '');
    }

    /**
     * @param  array<string, int>  $columns
     */
    private function assertSenjaColumns(array $columns): void
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (! array_key_exists($column, $columns)) {
                throw ValidationException::withMessages([
                    'file' => "This doesn't look like a Senja CSV export — the expected columns are missing.",
                ]);
            }
        }
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $columns
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row, array $columns, Client $client): ?array
    {
        $description = trim((string) $this->value($row, $columns, 'text'));

        if ($description === '') {
            return null;
        }

        $username = trim((string) $this->value($row, $columns, 'customer_name'));
        $subtitle = trim((string) $this->value($row, $columns, 'customer_tagline'))
            ?: trim((string) $this->value($row, $columns, 'customer_company'));
        $avatar = trim((string) $this->value($row, $columns, 'customer_avatar'));
        $title = trim((string) $this->value($row, $columns, 'title'));
        $date = trim((string) $this->value($row, $columns, 'date'));

        if (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            return null;
        }

        foreach ([$title, $username, $subtitle] as $text) {
            if (mb_strlen($text) > self::MAX_STRING_LENGTH) {
                return null;
            }
        }

        $timestamp = $this->parseDate($date) ?? now();

        return [
            'client_id' => $client->id,
            'contact_id' => null,
            'title' => $title !== '' ? $title : null,
            'description' => $description,
            'rating' => $this->parseRating($this->value($row, $columns, 'rating')),
            'avatar_url' => $this->avatarUrl($avatar),
            'username' => $username !== '' ? $username : 'Anonymous',
            'subtitle' => $subtitle !== '' ? $subtitle : null,
            'approved_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $columns
     */
    private function value(array $row, array $columns, string $key): ?string
    {
        if (! array_key_exists($key, $columns)) {
            return null;
        }

        return $row[$columns[$key]] ?? null;
    }

    /**
     * Senja stores unrated text testimonials as "0" or blank. The app only
     * accepts a star rating, so anything outside the valid range becomes null.
     */
    private function parseRating(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $rating = (int) $value;

        return ($rating >= Testimonial::RATING_MIN && $rating <= Testimonial::RATING_MAX) ? $rating : null;
    }

    /**
     * Only an https URL of 255 characters or less is copied. Otherwise the
     * model shows a generated avatar.
     */
    private function avatarUrl(string $value): ?string
    {
        if ($value === '' || mb_strlen($value) > self::MAX_STRING_LENGTH) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false || parse_url($value, PHP_URL_SCHEME) !== 'https') {
            return null;
        }

        return $value;
    }

    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
