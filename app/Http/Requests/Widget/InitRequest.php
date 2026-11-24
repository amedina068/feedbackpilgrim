<?php

namespace App\Http\Requests\Widget;

use App\Support\ContactPresenter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class InitRequest extends FormRequest
{
    /**
     * The widget client middleware already identified the client.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The contact fields that are columns of the contacts table. The other
     * fields of the contact become metadata (`HandlesInitRequests`).
     */
    public const CONTACT_COLUMNS = ['name', 'email', 'top_customer', 'mrr', 'ltv'];

    /**
     * The contact is sent flat ({email, name}) or nested ({contact: {email}}).
     * The publishable key is public, so each field has a type and a size limit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact' => ['required', 'array'],
            'contact.email' => ['required_without:contact.contact', 'nullable', 'string', 'email', 'max:255'],
            'contact.contact' => ['sometimes', 'array'],
            'contact.contact.email' => ['required_with:contact.contact', 'string', 'email', 'max:255'],
            ...$this->contactFieldRules('contact'),
            ...$this->contactFieldRules('contact.contact'),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function contactFieldRules(string $prefix): array
    {
        return [
            "{$prefix}.name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.avatar" => ['nullable', 'string', 'url:http,https', 'max:2048'],
            "{$prefix}.mrr" => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            "{$prefix}.ltv" => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    /**
     * The custom fields of the contact have the same limits as the metadata
     * of the REST API: no reserved `pilgrim_` key, at most 50 keys, a depth
     * of 3 and 16 KB.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $contact = $this->input('contact');

                if ($validator->errors()->isNotEmpty() || ! is_array($contact)) {
                    return;
                }

                $contactData = array_key_exists('contact', $contact) ? $contact['contact'] : $contact;

                if (! is_array($contactData)) {
                    return;
                }

                try {
                    ContactPresenter::guardMetadata(Arr::except($contactData, self::CONTACT_COLUMNS));
                } catch (ValidationException $exception) {
                    $validator->errors()->add('contact', $exception->validator->errors()->first('metadata'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact.required' => 'Send the signed-in user as "contact" with at least an email.',
            'contact.email.required_without' => 'Send the email of the signed-in user in "contact.email".',
            'contact.contact.email.required_with' => 'Send the email of the signed-in user in "contact.contact.email".',
        ];
    }

    /**
     * pilgrim.js does not send an Accept header, so always answer with JSON.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
