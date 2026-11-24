<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class IframeReleaseNoteController extends Controller
{
    public function index(Request $request)
    {
        $client = $request->client;

        return view('iframes.release-notes', compact('client'));
    }
}
