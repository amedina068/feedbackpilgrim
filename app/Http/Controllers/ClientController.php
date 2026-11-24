<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\ClientSetting;
use App\Support\UploadFileName;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function update(UpdateClientRequest $request)
    {
        $client = Client::query()->findOrFail($request->attributes->get('fp_client_id'));

        if ($request->filled('name')) {
            $client->update($request->only('name'));
        }

        if ($request->has('domain')) {
            $this->updateWebsite($client, $request->input('domain'));
        }

        if ($request->file('logo')) {
            $filePath = Storage::disk('public')->putFileAs(
                "logos/{$client->id}",
                $request->file('logo'),
                UploadFileName::for($request->file('logo')),
            );

            $client->update([
                'logo' => Storage::disk('public')->url($filePath),
            ]);
        }

        return back()->with('success', 'Your Project information has been updated');
    }

    private function updateWebsite(Client $client, ?string $website): void
    {
        if ($website === null) {
            $client->settings()->domain()->delete();

            return;
        }

        $client->settings()->updateOrCreate(['name' => ClientSetting::NAME_DOMAIN], ['value' => $website]);
    }
}
