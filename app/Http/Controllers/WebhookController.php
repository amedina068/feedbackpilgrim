<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use App\Rules\PublicHttpsUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebhookController extends Controller
{
    public function index(Request $request): View
    {
        $webhooks = Webhook::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with(['deliveries' => fn ($query) => $query->latest()->limit(5)])
            ->latest()
            ->get();

        return view('webhooks.index', compact('webhooks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateWebhook($request);

        Webhook::query()->create(array_merge($data, [
            'client_id' => $request->attributes->get('fp_client_id'),
            'secret' => Str::random(40),
            'is_active' => true,
        ]));

        return back()->with('success', 'Webhook created successfully');
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorizeWebhook($request, $webhook);

        $webhook->update($this->validateWebhook($request) + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Webhook updated successfully');
    }

    public function destroy(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorizeWebhook($request, $webhook);

        $webhook->delete();

        return back()->with('success', 'Webhook deleted successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWebhook(Request $request): array
    {
        return $request->validate([
            'url' => ['required', 'url', 'max:2048', new PublicHttpsUrl],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(Webhook::EVENTS)],
        ]);
    }

    private function authorizeWebhook(Request $request, Webhook $webhook): void
    {
        abort_unless((int) $webhook->client_id === (int) $request->attributes->get('fp_client_id'), 404);
    }
}
