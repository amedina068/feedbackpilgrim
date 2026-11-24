<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Stage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StageController extends Controller
{
    public function index(Request $request): View
    {
        $boards = Board::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('position')
            ->get();

        $activeBoard = $request->filled('board')
            ? $boards->firstWhere('id', (int) $request->board)
            : $boards->first();

        abort_if($activeBoard === null, 404);

        $stages = Stage::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->orderBy('position')
            ->with(['posts' => fn ($query) => $query
                ->where('board_id', $activeBoard->id)
                ->withCount('votes')
                ->orderBy('stage_position')])
            ->get();

        return view('stages.index', compact('stages', 'boards', 'activeBoard'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateStage($request);

        $position = (int) Stage::query()->where('client_id', $request->attributes->get('fp_client_id'))->max('position');

        Stage::query()->create(array_merge($data, [
            'client_id' => $request->attributes->get('fp_client_id'),
            'position' => $position + 1,
        ]));

        return back()->with('success', 'Status created successfully');
    }

    public function update(Request $request, Stage $stage): RedirectResponse
    {
        $this->authorizeStage($request, $stage);

        $stage->update($this->validateStage($request));

        return back()->with('success', 'Status updated successfully');
    }

    public function destroy(Request $request, Stage $stage): RedirectResponse
    {
        $this->authorizeStage($request, $stage);

        if ($this->isLastCompleteStage($request, $stage)) {
            return back()->withErrors(['stage' => 'You cannot delete the only "complete" status.']);
        }

        $fallback = Stage::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->where('id', '!=', $stage->id)
            ->ofType(Stage::TYPE_OPEN)
            ->orderBy('position')
            ->first();

        $stage->posts()->update(['stage_id' => $fallback?->id]);

        $stage->delete();

        return back()->with('success', 'Status deleted successfully');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stages' => ['required', 'array'],
            'stages.*' => [
                'integer',
                Rule::exists('stages', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
        ]);

        foreach (array_values($validated['stages']) as $position => $stageId) {
            Stage::query()
                ->where('client_id', $request->attributes->get('fp_client_id'))
                ->where('id', $stageId)
                ->update(['position' => $position + 1]);
        }

        return back()->with('success', 'Statuses reordered successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStage(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'type' => ['required', Rule::in(Stage::TYPES)],
            'show_on_roadmap' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeStage(Request $request, Stage $stage): void
    {
        abort_unless((int) $stage->client_id === (int) $request->attributes->get('fp_client_id'), 404);
    }

    private function isLastCompleteStage(Request $request, Stage $stage): bool
    {
        if (! $stage->isComplete()) {
            return false;
        }

        return Stage::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->ofType(Stage::TYPE_COMPLETE)
            ->count() <= 1;
    }
}
