<?php

namespace App\Repositories\Eloquent;

use App\Models\VendorEmail;
use App\Repositories\Contracts\VendorEmailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VendorEmailRepository implements VendorEmailRepositoryInterface
{
    public function paginate(int $perPage = 15, ?string $vendorId = null): LengthAwarePaginator
    {
        $query = VendorEmail::query()->orderByDesc('created_at');

        if ($vendorId !== null) {
            $query->where('vendor_id', $vendorId);
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): ?VendorEmail
    {
        return VendorEmail::query()->whereKey($id)->first();
    }

    public function create(array $attributes): VendorEmail
    {
        return VendorEmail::query()->create($attributes);
    }

    public function update(VendorEmail $vendorEmail, array $attributes): VendorEmail
    {
        $vendorEmail->update($attributes);

        return $vendorEmail->fresh();
    }

    public function delete(VendorEmail $vendorEmail): bool
    {
        return (bool) $vendorEmail->delete();
    }
}
