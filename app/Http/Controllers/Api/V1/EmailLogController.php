<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\EmailLogDetailResource;
use App\Http\Resources\Api\V1\EmailLogResource;
use App\Models\EmailLog;
use App\Services\EmailLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailLogController extends BaseApiController
{
    public function __construct(
        protected EmailLogService $emailLogs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);

        $paginator = $this->emailLogs->paginate($perPage, [
            'search' => $request->query('search'),
            'vendor_id' => $request->query('vendor_id'),
            'processing_status' => $request->query('processing_status'),
        ]);

        return ApiResponse::fromResource(
            EmailLogResource::collection($paginator)
        );
    }

    public function show(EmailLog $email_log): JsonResponse
    {
        $log = $this->emailLogs->find((string) $email_log->getKey());

        if (! $log) {
            return ApiResponse::error('Email log not found.', 404);
        }

        return ApiResponse::success(new EmailLogDetailResource($log));
    }
}
