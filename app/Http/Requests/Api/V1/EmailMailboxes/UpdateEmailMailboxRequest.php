<?php

namespace App\Http\Requests\Api\V1\EmailMailboxes;

use App\EmailMonitoring\Enums\MailboxDriver;
use App\Models\EmailMailbox;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailMailboxRequest extends FormRequest
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

        $mailbox = $this->route('email_mailbox');
        if ($mailbox instanceof EmailMailbox && (string) $mailbox->organization_id !== (string) $organizationId) {
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
            'name' => ['sometimes', 'string', 'max:255'],
            'driver' => ['sometimes', 'string', Rule::in(array_map(fn (MailboxDriver $d) => $d->value, MailboxDriver::cases()))],
            'is_enabled' => ['sometimes', 'boolean'],
            'connection_config' => ['sometimes', 'array'],
            'connection_config.host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'connection_config.port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'connection_config.encryption' => ['sometimes', 'nullable', 'string', Rule::in(['ssl', 'tls', 'none'])],
            'connection_config.username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'connection_config.password' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'connection_config.folder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'connection_config.client_id' => ['sometimes', 'nullable', 'string', 'max:500'],
            'connection_config.client_secret' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'connection_config.refresh_token' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }
}
