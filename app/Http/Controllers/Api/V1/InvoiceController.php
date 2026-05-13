<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Api\V1\Invoices\UpdateInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends BaseApiController
{
    public function __construct(
        protected InvoiceService $invoices
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return ApiResponse::fromResource(
            InvoiceResource::collection($this->invoices->paginate($perPage))
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->create($request->validated(), $request->user());

        return ApiResponse::success(
            new InvoiceResource($invoice),
            'Invoice created.',
            Response::HTTP_CREATED
        );
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return ApiResponse::success(new InvoiceResource($invoice));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->update($invoice, $request->validated(), $request->user());

        return ApiResponse::success(new InvoiceResource($invoice), 'Invoice updated.');
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->invoices->delete($invoice);

        return ApiResponse::success(null, 'Invoice deleted.');
    }
}
