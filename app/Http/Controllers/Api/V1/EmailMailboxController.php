<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\EmailMailboxes\StoreEmailMailboxRequest;
use App\Http\Requests\Api\V1\EmailMailboxes\UpdateEmailMailboxRequest;
use App\Http\Resources\Api\V1\EmailMailboxResource;
use App\Models\EmailMailbox;
use App\Services\EmailMailboxService;
use App\Support\ApiResponse;
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

    public function destroy(EmailMailbox $email_mailbox): JsonResponse
    {
        $this->mailboxes->delete($email_mailbox);

        return ApiResponse::success(null, 'Mailbox deleted.');
    }
}
