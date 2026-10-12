<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\Concerns\NormalizesDkimSelector;
use Illuminate\Foundation\Http\FormRequest;

class RunPublicDnsCheckRequest extends FormRequest
{
    use NormalizesDkimSelector;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target' => ['required', 'string', 'max:2048'],
            'dkim_selector' => $this->dkimSelectorRules(),
        ];
    }
}
