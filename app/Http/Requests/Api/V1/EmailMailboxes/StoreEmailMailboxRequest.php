<?php

namespace App\Http\Requests\Api\V1\EmailMailboxes;

use App\EmailMonitoring\Enums\MailboxDriver;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailMailboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $organizationId = app(CurrentOrganization::class)->id();
        if (! $organizationId) {
            return false;
        }

        return $user->hasOrganizationRoleInOrganization((string) $organizationId, ['owner', 'admin']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', Rule::in(array_map(fn (MailboxDriver $d) => $d->value, MailboxDriver::cases()))],
            'is_enabled' => ['sometimes', 'boolean'],
            'connection_config' => ['required', 'array'],
            'connection_config.host' => ['required_if:driver,imap', 'nullable', 'string', 'max:255'],
            'connection_config.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'connection_config.encryption' => ['nullable', 'string', Rule::in(['ssl', 'tls', 'none'])],
            'connection_config.username' => ['required_if:driver,imap', 'nullable', 'string', 'max:255'],
            'connection_config.password' => ['required_if:driver,imap', 'nullable', 'string', 'max:2000'],
            'connection_config.folder' => ['nullable', 'string', 'max:255'],
            'connection_config.client_id' => ['required_if:driver,gmail_api', 'nullable', 'string', 'max:500'],
            'connection_config.client_secret' => ['required_if:driver,gmail_api', 'nullable', 'string', 'max:2000'],
            'connection_config.refresh_token' => ['required_if:driver,gmail_api', 'nullable', 'string', 'max:4000'],
        ];
    }
}
