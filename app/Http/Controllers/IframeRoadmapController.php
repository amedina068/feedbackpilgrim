<?php

namespace App\Http\Controllers;

use App\Support\RoadmapData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IframeRoadmapController extends Controller
{
    public function index(Request $request): View
    {
        $client = $request->client;

        ['boards' => $boards, 'stages' => $stages] = RoadmapData::build(
            $client,
            $request->filled('board') ? $request->board : null,
        );

        return view('iframes.roadmap', compact('client', 'stages', 'boards'));
    }
}
