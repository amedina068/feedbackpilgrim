<?php

namespace App\Http\Requests;

use App\Actions\SendingDomains\ConnectSendingDomain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSendingDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain' => $this->normalizeDomain((string) $this->input('domain')),
            'sender_local_part' => Str::lower(trim((string) $this->input('sender_local_part'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'domain' => [
                'required',
                'string',
                'max:253',
                // The regex also keeps { and } out of a value that the page renders inside the Vue #app.
                'regex:/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                Rule::notIn(config('mail.free_mail_domains', [])),
                $this->notReservedDomain(...),
                Rule::unique('sending_domains', 'domain'),
            ],
            // The regex also keeps { and } out of a value that the page renders inside the Vue #app.
            'sender_local_part' => ['required', 'string', 'regex:/^[a-z0-9._+-]{1,64}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.regex' => 'Enter a domain like example.com.',
            'domain.not_in' => 'Use a domain that you own. Free email domains like gmail.com do not work.',
            'domain.unique' => ConnectSendingDomain::ALREADY_CONNECTED_MESSAGE,
            'sender_local_part.regex' => 'Use only letters, numbers, dots, dashes, plus signs and underscores. The maximum is 64 characters.',
        ];
    }

    /**
     * Reject the app domain, the domain of the default sender, and their subdomains.
     */
    private function notReservedDomain(string $attribute, mixed $value, Closure $fail): void
    {
        $reservedDomains = array_filter([
            Str::lower((string) config('app.domain')),
            Str::lower(Str::after((string) config('mail.from.address'), '@')),
        ]);

        foreach ($reservedDomains as $reservedDomain) {
            if ($value === $reservedDomain || Str::endsWith($value, '.'.$reservedDomain)) {
                $fail('Use your own domain. This domain belongs to FeedbackPilgrim.');

                return;
            }
        }
    }

    /**
     * Turn input like "https://www.Example.com./path" into "example.com".
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = Str::lower(trim($domain));
        $domain = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $domain);
        $domain = (string) preg_replace('#[/?\#].*$#', '', $domain);
        $domain = rtrim($domain, '.');

        return Str::startsWith($domain, 'www.') ? Str::after($domain, 'www.') : $domain;
    }
}
