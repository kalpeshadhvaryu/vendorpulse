<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VendorService
{
    public function __construct(
        protected VendorRepositoryInterface $vendors,
        protected CurrentOrganization $currentOrganization
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->vendors->paginateWithFilters($filters, $perPage);
    }

    public function find(string $id, bool $withTrashed = false): ?Vendor
    {
        return $this->vendors->find($id, $withTrashed);
    }

    public function findTrashed(string $id): ?Vendor
    {
        return $this->vendors->findTrashed($id);
    }

    public function create(array $data, User $actor): Vendor
    {
        $data['company_id'] = $this->currentOrganization->id();
        $data['created_by'] = $actor->id;
        $data['updated_by'] = $actor->id;

        return $this->vendors->create($data);
    }

    public function update(Vendor $vendor, array $data, User $actor): Vendor
    {
        $data['updated_by'] = $actor->id;

        return $this->vendors->update($vendor, $data);
    }

    public function delete(Vendor $vendor): bool
    {
        return $this->vendors->delete($vendor);
    }

    public function restore(Vendor $vendor): bool
    {
        return $this->vendors->restore($vendor);
    }
}
