<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\VendorEmails\IndexVendorEmailRequest;
use App\Http\Requests\Api\V1\VendorEmails\StoreVendorEmailRequest;
use App\Http\Requests\Api\V1\VendorEmails\UpdateVendorEmailRequest;
use App\Http\Resources\Api\V1\VendorEmailResource;
use App\Models\VendorEmail;
use App\Services\VendorEmailService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VendorEmailController extends BaseApiController
{
    public function __construct(
        protected VendorEmailService $vendorEmails
    ) {}

    public function index(IndexVendorEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 15), 100);
        $vendorId = isset($validated['vendor_id']) ? (string) $validated['vendor_id'] : null;

        return ApiResponse::fromResource(
            VendorEmailResource::collection($this->vendorEmails->paginate($perPage, $vendorId))
        );
    }

    public function store(StoreVendorEmailRequest $request): JsonResponse
    {
        $vendor = $request->vendor();
        $email = $this->vendorEmails->create(
            $request->safe()->except('vendor_id'),
            $request->user(),
            $vendor
        );

        return ApiResponse::success(
            new VendorEmailResource($email),
            'Vendor email created.',
            Response::HTTP_CREATED
        );
    }

    public function show(VendorEmail $vendorEmail): JsonResponse
    {
        return ApiResponse::success(new VendorEmailResource($vendorEmail));
    }

    public function update(UpdateVendorEmailRequest $request, VendorEmail $vendorEmail): JsonResponse
    {
        $vendorEmail = $this->vendorEmails->update($vendorEmail, $request->validated(), $request->user());

        return ApiResponse::success(new VendorEmailResource($vendorEmail), 'Vendor email updated.');
    }

    public function destroy(VendorEmail $vendorEmail): JsonResponse
    {
        $this->vendorEmails->delete($vendorEmail);

        return ApiResponse::success(null, 'Vendor email deleted.');
    }
}
