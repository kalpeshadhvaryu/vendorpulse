<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Vendors\ListVendorsRequest;
use App\Http\Requests\Api\V1\Vendors\StoreVendorRequest;
use App\Http\Requests\Api\V1\Vendors\UpdateVendorRequest;
use App\Http\Resources\Api\V1\VendorResource;
use App\Models\Vendor;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\VendorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VendorController extends BaseApiController
{
    public function __construct(
        protected VendorService $vendors,
        protected NotificationDispatchService $notifications
    ) {}

    public function index(ListVendorsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 15), 100);
        $filters = $request->filters();

        return ApiResponse::fromResource(
            VendorResource::collection($this->vendors->paginateWithFilters($filters, $perPage))
        );
    }

    public function store(StoreVendorRequest $request): JsonResponse
    {
        $vendor = $this->vendors->create($request->validated(), $request->user());

        $this->notifications->notify(
            $request->user(),
            'vendor.created',
            ['vendor_id' => $vendor->id, 'name' => $vendor->name]
        );

        return ApiResponse::success(
            new VendorResource($vendor),
            'Vendor created.',
            Response::HTTP_CREATED
        );
    }

    public function show(Vendor $vendor): JsonResponse
    {
        return ApiResponse::success(new VendorResource($vendor));
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): JsonResponse
    {
        $vendor = $this->vendors->update($vendor, $request->validated(), $request->user());

        return ApiResponse::success(new VendorResource($vendor), 'Vendor updated.');
    }

    public function destroy(Request $request, Vendor $vendor): JsonResponse
    {
        $this->vendors->delete($vendor);

        $this->notifications->notify(
            $request->user(),
            'vendor.deleted',
            ['vendor_id' => $vendor->id]
        );

        return ApiResponse::success(null, 'Vendor deleted.');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $vendor = $this->vendors->findTrashed($id);

        if (! $vendor) {
            return ApiResponse::error('Trashed vendor not found.', Response::HTTP_NOT_FOUND);
        }

        $this->vendors->restore($vendor);

        return ApiResponse::success(
            new VendorResource($vendor->fresh()),
            'Vendor restored.'
        );
    }
}
