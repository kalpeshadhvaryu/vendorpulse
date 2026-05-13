<?php

namespace App\Repositories\Contracts;

use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VendorRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function find(string $id, bool $withTrashed = false): ?Vendor;

    public function findTrashed(string $id): ?Vendor;

    public function create(array $attributes): Vendor;

    public function update(Vendor $vendor, array $attributes): Vendor;

    public function delete(Vendor $vendor): bool;

    public function restore(Vendor $vendor): bool;
}
