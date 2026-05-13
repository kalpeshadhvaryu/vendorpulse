<?php

namespace App\Repositories\Contracts;

use App\Models\VendorEmail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VendorEmailRepositoryInterface
{
    public function paginate(int $perPage = 15, ?string $vendorId = null): LengthAwarePaginator;

    public function find(string $id): ?VendorEmail;

    public function create(array $attributes): VendorEmail;

    public function update(VendorEmail $vendorEmail, array $attributes): VendorEmail;

    public function delete(VendorEmail $vendorEmail): bool;
}
