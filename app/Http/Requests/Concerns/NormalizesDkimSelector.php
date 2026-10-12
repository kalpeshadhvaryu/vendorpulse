<?php

namespace App\Http\Requests\Concerns;

use App\Services\Vapt\Email\DkimSelectorParser;

trait NormalizesDkimSelector
{
    protected function prepareForValidation(): void
    {
        $value = $this->input('dkim_selector');

        if (is_string($value)) {
            $this->merge(['dkim_selector' => DkimSelectorParser::parse($value)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function dkimSelectorRules(): array
    {
        return ['nullable', 'string', 'max:63', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = 'We could not find a DKIM selector in that. Enter just the selector (for example google or selector1), '
            .'the DNS name (selector1._domainkey.example.com), or paste the DKIM-Signature header from an email you sent.';

        return [
            'dkim_selector.max' => $message,
            'dkim_selector.regex' => $message,
        ];
    }
}
