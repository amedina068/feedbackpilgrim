<?php

namespace App\Http\Controllers;

use App\Support\WidgetContactToken;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IframeTestimonialController extends Controller
{
    /**
     * The widget gets the contact token only when the server issued it for
     * this client. Any other query value never reaches the page.
     */
    public function index(Request $request): View
    {
        $token = $request->query('contact_token');

        $contactToken = WidgetContactToken::resolve($token, $request->client) ? $token : null;

        return view('iframe-testimonials.create', compact('contactToken'));
    }
}
