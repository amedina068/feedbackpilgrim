<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Traits\HandlesInitRequests;
use App\Models\Client;
use App\Support\ClientContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InitController extends Controller
{
    use HandlesInitRequests;

    public function index(Request $request): JsonResponse
    {
        $clientId = ClientContext::fromToken(Auth::user()?->currentAccessToken())->clientId;
        $client = Client::find($clientId);

        $response = [];

        if ($request->has('contact') && ! empty($request->contact) && (isset($request->contact['email']) || isset($request->contact['contact']['email']))) {
            $contact = $this->handleContact($request, $client);
            $response['auth_url'] = $contact->generateAuthUrl();
        }

        return response()->json($response);
    }
}
