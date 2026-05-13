<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEmail;
use App\Repositories\Contracts\VendorEmailRepositoryInterface;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VendorEmailService
{
    public function __construct(
        protected VendorEmailRepositoryInterface $vendorEmails,
        protected CurrentOrganization $currentOrganization
    ) {}

    public function paginate(int $perPage = 15, ?string $vendorId = null): LengthAwarePaginator
    {
        return $this->vendorEmails->paginate($perPage, $vendorId);
    }

    public function find(string $id): ?VendorEmail
    {
        return $this->vendorEmails->find($id);
    }

    public function create(array $data, User $actor, Vendor $vendor): VendorEmail
    {
        $data['organization_id'] = $this->currentOrganization->id();
        $data['vendor_id'] = $vendor->id;
        $data['created_by'] = $actor->id;
        $data['updated_by'] = $actor->id;

        return $this->vendorEmails->create($data);
    }

    public function update(VendorEmail $vendorEmail, array $data, User $actor): VendorEmail
    {
        $data['updated_by'] = $actor->id;

        return $this->vendorEmails->update($vendorEmail, $data);
    }

    public function delete(VendorEmail $vendorEmail): bool
    {
        return $this->vendorEmails->delete($vendorEmail);
    }
}
