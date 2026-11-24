<?php

namespace App\Http\Controllers;

use App\Actions\SendingDomains\CheckSendingDomain;
use App\Actions\SendingDomains\ConnectSendingDomain;
use App\Actions\SendingDomains\RemoveSendingDomain;
use App\Actions\SendingDomains\RetrySendingDomain;
use App\Enums\SendingDomainStatus;
use App\Exceptions\SendingDomainException;
use App\Http\Requests\StoreSendingDomainRequest;
use App\Models\Client;
use App\Models\SendingDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SendingDomainController extends Controller
{
    public function show(Request $request): View
    {
        $client = Client::query()->findOrFail($request->attributes->get('fp_client_id'));

        return view('settings.email', [
            'sendingDomain' => $client->sendingDomain,
            'mailSender' => $client->mailSender(),
            'fallbackAddress' => config('mail.from.address'),
        ]);
    }

    public function store(StoreSendingDomainRequest $request, ConnectSendingDomain $connect): RedirectResponse
    {
        if ($this->sendingDomainForClient($request)) {
            return back()->withErrors(['domain' => 'You already have a sending domain. Remove it before you connect a new one.']);
        }

        try {
            $connect($request->attributes->get('fp_client_id'), $request->validated('domain'), $request->validated('sender_local_part'));
        } catch (SendingDomainException $exception) {
            return back()->withInput()->withErrors(['domain' => $exception->getMessage()]);
        }

        return redirect()->route('settings.email')->with('success', 'Domain connected. Add the DNS records below to your DNS provider.');
    }

    public function check(Request $request, CheckSendingDomain $check): RedirectResponse
    {
        $sendingDomain = $this->sendingDomainOrRedirect($request);

        try {
            $check($sendingDomain);
        } catch (SendingDomainException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('success', match ($sendingDomain->status) {
            SendingDomainStatus::Verified => "Verified. Emails now go out from {$sendingDomain->sender_local_part}@{$sendingDomain->domain}.",
            SendingDomainStatus::Pending => 'Still waiting for the DNS records.',
            SendingDomainStatus::Failed => 'SES could not verify this domain.',
        });
    }

    public function retry(Request $request, RetrySendingDomain $retry): RedirectResponse
    {
        $sendingDomain = $this->sendingDomainOrRedirect($request);

        if ($sendingDomain->status !== SendingDomainStatus::Failed) {
            return back()->withErrors(['domain' => RetrySendingDomain::ONLY_FAILED_MESSAGE]);
        }

        try {
            $retry($sendingDomain);
        } catch (SendingDomainException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return back()->with('success', 'New DNS records are ready. Replace the old CNAME records with these.');
    }

    public function destroy(Request $request, RemoveSendingDomain $remove): RedirectResponse
    {
        $sendingDomain = $this->sendingDomainOrRedirect($request);

        try {
            $remove($sendingDomain);
        } catch (SendingDomainException $exception) {
            return back()->withErrors(['domain' => $exception->getMessage()]);
        }

        return redirect()->route('settings.email')->with('success', 'Domain removed. Emails now go out from '.config('mail.from.address').'.');
    }

    /**
     * Return the domain of the current client. With no domain, stop the request
     * with a redirect to the email page, so no SES call runs.
     */
    private function sendingDomainOrRedirect(Request $request): SendingDomain
    {
        return $this->sendingDomainForClient($request)
            ?? abort(redirect()->route('settings.email')->withErrors(['domain' => 'Connect a domain first.']));
    }

    private function sendingDomainForClient(Request $request): ?SendingDomain
    {
        return SendingDomain::query()->where('client_id', $request->attributes->get('fp_client_id'))->first();
    }
}
