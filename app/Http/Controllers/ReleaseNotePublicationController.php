<?php

namespace App\Http\Controllers;

use App\Models\ReleaseNote;
use Illuminate\Http\Request;

class ReleaseNotePublicationController extends Controller
{
    public function store(Request $request, $releaseNoteId)
    {
        $releaseNote = $this->releaseNote($request, $releaseNoteId);

        $releaseNote->publish();

        return redirect("release-notes/{$releaseNote->id}")->with('success', 'Release note published');
    }

    public function destroy(Request $request, $releaseNoteId)
    {
        $releaseNote = $this->releaseNote($request, $releaseNoteId);

        $releaseNote->unpublish();

        return redirect("release-notes/{$releaseNote->id}")->with('success', 'Release note unpublished');
    }

    private function releaseNote(Request $request, $releaseNoteId): ReleaseNote
    {
        return ReleaseNote::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->findOrFail($releaseNoteId);
    }
}
