<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateClientController extends Controller
{
    public function update(Request $request)
    {
        $client = Auth::user()
            ->clients()
            ->where('client_user.client_id', $request->client_id)
            ->firstOrFail();

        session([
            'fp_client_id' => $client->id,
        ]);

        return redirect('dashboard');
    }
}
