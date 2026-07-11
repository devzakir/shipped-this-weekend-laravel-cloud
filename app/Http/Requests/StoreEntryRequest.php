<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required', 'url', 'max:2048',
                Rule::unique('entries', 'url'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $host = strtolower((string) parse_url($value, PHP_URL_HOST));
                    if (! str_ends_with($host, '.laravel.cloud')) {
                        $fail('The URL must be a *.laravel.cloud address.');
                    }
                },
            ],
            'tagline' => ['required', 'string', 'max:80'],
            'author_name' => ['required', 'string', 'max:100'],
            'x_handle' => ['nullable', 'string', 'max:50'],
            'website' => ['prohibited'], // honeypot: must be empty/absent
        ];
    }

    public function messages(): array
    {
        return ['website.prohibited' => 'Spam detected.'];
    }
}
