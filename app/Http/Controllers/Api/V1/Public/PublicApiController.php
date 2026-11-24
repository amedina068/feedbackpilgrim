<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Support\ClientContext;
use Illuminate\Http\Request;

abstract class PublicApiController extends Controller
{
    protected function clientContext(Request $request): ClientContext
    {
        $context = $request->attributes->get('clientContext');

        if ($context instanceof ClientContext) {
            return $context;
        }

        return ClientContext::fromToken($request->user()?->currentAccessToken());
    }
}
