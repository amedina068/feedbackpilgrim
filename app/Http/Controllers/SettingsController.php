<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class SettingsController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('settings.api-tokens');
    }

    public function project(Request $request): View
    {
        $client = Client::query()->findOrFail($request->attributes->get('fp_client_id'));

        $website = $client->settings()->domain()->value('value');

        return view('settings.project', compact('client', 'website'));
    }

    public function apiTokens(Request $request): View
    {
        $clientId = $request->attributes->get('fp_client_id');

        $apiKeys = PersonalAccessToken::where('client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->get();

        $mcpUrl = rtrim((string) config('app.url'), '/').'/mcp';

        return view('settings.api-tokens', compact('apiKeys', 'mcpUrl'));
    }

    public function testimonials(Request $request): View
    {
        $clientId = $request->attributes->get('fp_client_id');

        $testimonialRating = ClientSetting::where('client_id', $clientId)
            ->testimonialRating()
            ->first();

        $testimonialAutomation = ClientSetting::where('client_id', $clientId)
            ->testimonialAutomation()
            ->first();

        $testimonialEmailBody = ClientSetting::where('client_id', $clientId)
            ->testimonialEmailBody()
            ->first();

        return view('settings.testimonials', compact('testimonialRating', 'testimonialAutomation', 'testimonialEmailBody'));
    }

    public function integrations(Request $request): View
    {
        $clientId = $request->attributes->get('fp_client_id');

        $slackWebhookUrl = ClientSetting::where('client_id', $clientId)
            ->slackWebhook()
            ->value('value');

        return view('settings.integrations', compact('slackWebhookUrl'));
    }

    public function updateIntegrations(Request $request): RedirectResponse
    {
        $clientId = $request->attributes->get('fp_client_id');

        $validated = $request->validate([
            'slack_webhook_url' => ['nullable', 'url', 'starts_with:https://hooks.slack.com/', 'max:255'],
        ]);

        $url = $validated['slack_webhook_url'] ?? null;

        if (! $url) {
            ClientSetting::where('client_id', $clientId)
                ->where('name', ClientSetting::NAME_SLACK_WEBHOOK_URL)
                ->delete();

            return back()->with('success', 'Integrations updated successfully.');
        }

        ClientSetting::updateOrCreate(
            [
                'client_id' => $clientId,
                'name' => ClientSetting::NAME_SLACK_WEBHOOK_URL,
            ],
            ['value' => $url]
        );

        return back()->with('success', 'Integrations updated successfully.');
    }

    public function update(Request $request)
    {
        $clientId = $request->attributes->get('fp_client_id');

        $request->validate([
            'testimonial_rating' => 'sometimes|boolean',
            'testimonial_automation' => 'sometimes|boolean',
            'testimonial_email_body' => 'nullable|required_with:testimonial_automation|string|max:5000',
        ], [
            'testimonial_email_body.required_with' => 'Write the email body to send testimonial requests.',
        ]);

        ClientSetting::updateOrCreate(
            [
                'client_id' => $clientId,
                'name' => ClientSetting::NAME_TESTIMONIAL_RATING,
            ],
            ['value' => $request->testimonial_rating ? '1' : '0']
        );

        ClientSetting::updateOrCreate(
            [
                'client_id' => $clientId,
                'name' => ClientSetting::NAME_TESTIMONIAL_AUTOMATION,
            ],
            ['value' => $request->testimonial_automation ? '1' : '0']
        );

        ClientSetting::updateOrCreate(
            [
                'client_id' => $clientId,
                'name' => ClientSetting::NAME_TESTIMONIAL_EMAIL_BODY,
            ],
            ['value' => $request->testimonial_email_body ?? '']
        );

        return back()->with('success', 'Settings updated successfully.');
    }
}
