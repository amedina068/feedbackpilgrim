<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    /**
     * A request changes the name, the logo, or both. An empty website removes the website.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['string', 'max:255', 'required_without:logo'],
            'logo' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:1000', 'required_without:name'],
            'domain' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.image' => 'The logo must be a JPG, JPEG, PNG, GIF, or WebP image.',
            'logo.mimes' => 'The logo must be a JPG, JPEG, PNG, GIF, or WebP image.',
        ];
    }
}
