<?php

namespace App\Support;

use App\Models\Board;
use App\Models\Client;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Collection;

class RoadmapData
{
    /**
     * Build the public roadmap payload for a client: its public boards and the
     * roadmap stages with their public, non-archived posts (optionally filtered
     * to one board and annotated with the current contact's vote).
     *
     * @return array{boards: Collection<int, Board>, stages: Collection<int, Stage>, boardId: int|null}
     */
    public static function build(Client $client, ?string $boardSlug = null, ?int $contactId = null): array
    {
        $boards = Board::query()
            ->where('client_id', $client->id)
            ->public()
            ->orderBy('position')
            ->get();

        $boardId = $boardSlug
            ? $boards->firstWhere('slug', $boardSlug)?->id
            : null;

        $stages = Stage::query()
            ->where('client_id', $client->id)
            ->onRoadmap()
            ->with(['posts' => function ($query) use ($boardId, $contactId) {
                $query->public()
                    ->whereNull('archived_at')
                    ->forBoard($boardId)
                    ->withCount('votes')
                    ->votedByContact($contactId)
                    ->orderByDesc('votes_count');
            }])
            ->get();

        return compact('boards', 'stages', 'boardId');
    }
}
