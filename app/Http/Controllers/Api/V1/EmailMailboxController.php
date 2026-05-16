<?php

namespace App\Http\Controllers\Api\V1;

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

    public function destroy(Request $request, EmailMailbox $email_mailbox): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $organizationId = app(CurrentOrganization::class)->id();

        if (! $user || ! $organizationId || (string) $email_mailbox->organization_id !== (string) $organizationId) {
            return ApiResponse::error('Mailbox not found.', Response::HTTP_NOT_FOUND);
        }

        if (! $user->isAdmin() && ! $user->hasOrganizationRoleInOrganization((string) $organizationId, ['owner', 'admin'])) {
            return ApiResponse::error('You do not have permission to manage mailboxes for this organization.', Response::HTTP_FORBIDDEN);
        }

        $this->mailboxes->delete($email_mailbox);

        return ApiResponse::success(null, 'Mailbox deleted.');
    }
}
