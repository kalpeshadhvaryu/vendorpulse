<?php

namespace App\Http\Controllers\Api\V1;

use App\EmailMonitoring\Contracts\MailboxConnectorFactoryInterface;
use App\Http\Requests\Api\V1\EmailMailboxes\StoreEmailMailboxRequest;
use App\Http\Requests\Api\V1\EmailMailboxes\UpdateEmailMailboxRequest;
use App\Http\Resources\Api\V1\EmailMailboxResource;
use App\Models\EmailMailbox;
use App\Models\User;
use App\Services\EmailMailboxService;
use App\Support\ApiResponse;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EmailMailboxController extends BaseApiController
{
    public function __construct(
        protected EmailMailboxService $mailboxes
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return ApiResponse::fromResource(
            EmailMailboxResource::collection($this->mailboxes->paginate($perPage))
        );
    }

    public function store(StoreEmailMailboxRequest $request): JsonResponse
    {
        $mailbox = $this->mailboxes->create($request->validated(), $request->user());

        return ApiResponse::success(
            new EmailMailboxResource($mailbox),
            'Mailbox created.',
            Response::HTTP_CREATED
        );
    }

    public function show(EmailMailbox $email_mailbox): JsonResponse
    {
        return ApiResponse::success(new EmailMailboxResource($email_mailbox));
    }

    public function update(UpdateEmailMailboxRequest $request, EmailMailbox $email_mailbox): JsonResponse
    {
        $mailbox = $this->mailboxes->update($email_mailbox, $request->validated(), $request->user());

        return ApiResponse::success(new EmailMailboxResource($mailbox), 'Mailbox updated.');
    }

    public function testConnection(
        Request $request,
        EmailMailbox $email_mailbox,
        MailboxConnectorFactoryInterface $connectorFactory,
    ): JsonResponse {
        $authError = $this->authorizeMailboxManagement($request, $email_mailbox);
        if ($authError !== null) {
            return $authError;
        }

        try {
            $connector = $connectorFactory->forMailbox($email_mailbox);
            $connector->healthCheck($email_mailbox);

            $email_mailbox->update([
                'last_error' => null,
                'last_polled_at' => now(),
            ]);

            return ApiResponse::success([
                'ok' => true,
            ], 'Mailbox connection successful.');
        } catch (Throwable $e) {
            $email_mailbox->update([
                'last_polled_at' => now(),
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            $detail = $e->getMessage();
            $responseMessage = str_starts_with($detail, 'IMAP ')
                || str_starts_with($detail, 'Gmail ')
                ? $detail
                : 'Mailbox connection failed: '.$detail;

            return ApiResponse::error($responseMessage, Response::HTTP_UNPROCESSABLE_ENTITY);
        } finally {
            if (function_exists('imap_errors')) {
                imap_errors();
                imap_alerts();
            }
        }
    }

    public function destroy(Request $request, EmailMailbox $email_mailbox): JsonResponse
    {
        $authError = $this->authorizeMailboxManagement($request, $email_mailbox);
        if ($authError !== null) {
            return $authError;
        }

        $this->mailboxes->delete($email_mailbox);

        return ApiResponse::success(null, 'Mailbox deleted.');
    }

    private function authorizeMailboxManagement(Request $request, EmailMailbox $mailbox): ?JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Mailbox not found.', Response::HTTP_NOT_FOUND);
        }

        $currentOrganization = app(CurrentOrganization::class);
        $mailboxOrganizationId = (string) $mailbox->organization_id;

        if ($user->isAdmin()) {
            if (! $currentOrganization->id()) {
                $currentOrganization->set($mailbox->organization);
            }

            return null;
        }

        $organizationId = $currentOrganization->id();

        if (! $organizationId || $mailboxOrganizationId !== (string) $organizationId) {
            return ApiResponse::error(
                'Mailbox not found. Select the organization that owns this mailbox in the header switcher.',
                Response::HTTP_NOT_FOUND
            );
        }

        if (! $user->hasOrganizationRoleInOrganization($mailboxOrganizationId, ['owner', 'admin'])) {
            return ApiResponse::error('You do not have permission to manage mailboxes for this organization.', Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
