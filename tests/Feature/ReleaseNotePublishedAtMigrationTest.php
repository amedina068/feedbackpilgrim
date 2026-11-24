<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Before this migration, the Publish button set only `status`. The public site
 * reads only `published_at`. The migration copies the publication into
 * `published_at` and removes `status`.
 */
class ReleaseNotePublishedAtMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_11_18_000100_make_published_at_the_release_note_switch.php';

    public function test_a_published_note_without_a_date_gets_the_date_of_its_last_change(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION);
        $migration->down();

        $published = $this->insertNote('published', null, '2026-03-04 05:06:07');
        $draft = $this->insertNote('draft', null, '2026-03-04 05:06:07');
        $dated = $this->insertNote('published', '2026-01-02 03:04:05', '2026-03-04 05:06:07');

        $migration->up();

        $this->assertSame('2026-03-04 05:06:07', $this->publishedAt($published));
        $this->assertNull($this->publishedAt($draft));
        $this->assertSame('2026-01-02 03:04:05', $this->publishedAt($dated));
        $this->assertFalse(Schema::hasColumn('release_notes', 'status'));
    }

    public function test_down_restores_the_status_from_the_date(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION);

        $published = DB::table('release_notes')->insertGetId($this->row(['published_at' => '2026-01-02 03:04:05']));
        $draft = DB::table('release_notes')->insertGetId($this->row(['published_at' => null]));

        $migration->down();

        $this->assertSame('published', DB::table('release_notes')->where('id', $published)->value('status'));
        $this->assertSame('draft', DB::table('release_notes')->where('id', $draft)->value('status'));
    }

    private function insertNote(string $status, ?string $publishedAt, string $updatedAt): int
    {
        return DB::table('release_notes')->insertGetId($this->row([
            'status' => $status,
            'published_at' => $publishedAt,
            'updated_at' => $updatedAt,
        ]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function row(array $attributes): array
    {
        return [
            'client_id' => 1,
            'user_id' => 1,
            'title' => 'Note',
            'content' => 'Body',
            'slug' => 'note-'.uniqid(),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
            ...$attributes,
        ];
    }

    private function publishedAt(int $id): ?string
    {
        return DB::table('release_notes')->where('id', $id)->value('published_at');
    }
}
