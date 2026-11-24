<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardController extends Controller
{
    public function index(Request $request): View
    {
        $boards = Board::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->withCount('posts')
            ->orderBy('position')
            ->get();

        $client = Client::query()->find($request->attributes->get('fp_client_id'));

        return view('boards.index', compact('boards', 'client'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBoard($request);

        $position = (int) Board::query()->where('client_id', $request->attributes->get('fp_client_id'))->max('position');

        Board::query()->create(array_merge($data, [
            'client_id' => $request->attributes->get('fp_client_id'),
            'position' => $position + 1,
        ]));

        return back()->with('success', 'Board created successfully');
    }

    public function update(Request $request, Board $board): RedirectResponse
    {
        $this->authorizeBoard($request, $board);

        $board->update($this->validateBoard($request));

        return back()->with('success', 'Board updated successfully');
    }

    public function destroy(Request $request, Board $board): RedirectResponse
    {
        $this->authorizeBoard($request, $board);

        if (Board::query()->where('client_id', $request->attributes->get('fp_client_id'))->count() <= 1) {
            return back()->withErrors(['board' => 'You must keep at least one board.']);
        }

        $fallback = Board::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->where('id', '!=', $board->id)
            ->orderBy('position')
            ->first();

        $board->posts()->update(['board_id' => $fallback?->id]);

        $board->delete();

        return back()->with('success', 'Board deleted successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBoard(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeBoard(Request $request, Board $board): void
    {
        abort_unless((int) $board->client_id === (int) $request->attributes->get('fp_client_id'), 404);
    }
}
