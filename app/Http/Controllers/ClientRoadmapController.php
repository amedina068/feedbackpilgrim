<?php

namespace App\Http\Controllers;

use App\Support\RoadmapData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClientRoadmapController extends Controller
{
    public function index(Request $request): View
    {
        $client = $request->client;

        ['boards' => $boards, 'stages' => $stages] = RoadmapData::build(
            $client,
            $request->filled('board') ? $request->board : null,
            Auth::guard('contact')->id(),
        );

        return view('client-roadmap.index', compact('client', 'stages', 'boards'));
    }
}
