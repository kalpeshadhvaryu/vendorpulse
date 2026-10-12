<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\EmailDeliverabilityTest;
use App\Services\Vapt\Email\EmailDeliverabilityTestService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailDeliverabilityTestController extends BaseApiController
{
    public function __construct(private readonly EmailDeliverabilityTestService $tests) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->scoped($request)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (EmailDeliverabilityTest $test): array {
                $data = $this->tests->present($test);
                unset($data['result']);

                return $data;
            });

        return ApiResponse::success([
            'available' => $this->tests->isAvailable(),
            'items' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->tests->isAvailable()) {
            return ApiResponse::error('The email test mailbox is not configured on this server.', 503);
        }

        $test = $this->tests->create('dashboard', $this->organizationIdOrNull(), $request->user()?->id, $request->ip());

        return ApiResponse::success($this->tests->present($test), 'Email test created.', 201);
    }

    public function show(Request $request, string $emailDeliverabilityTest): JsonResponse
    {
        $test = $this->scoped($request)->whereKey($emailDeliverabilityTest)->first();

        if ($test === null) {
            return ApiResponse::error('Email test not found.', 404);
        }

        return ApiResponse::success($this->tests->present($this->tests->refresh($test)));
    }

    /**
     * @return Builder<EmailDeliverabilityTest>
     */
    private function scoped(Request $request): Builder
    {
        $organizationId = $this->organizationIdOrNull();

        return EmailDeliverabilityTest::query()
            ->where('source', 'dashboard')
            ->when(
                $organizationId !== null,
                fn (Builder $q) => $q->where('organization_id', $organizationId),
                fn (Builder $q) => $q->where('created_by', $request->user()?->id),
            );
    }
}
