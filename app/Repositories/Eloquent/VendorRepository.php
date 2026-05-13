<?php

namespace App\Repositories\Eloquent;

use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VendorRepository implements VendorRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Vendor::query();

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('billing_email', 'like', $term)
                    ->orWhere('support_email', 'like', $term)
                    ->orWhere('website', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        if (! empty($filters['vendor_type'])) {
            $query->whereIn('vendor_type', (array) $filters['vendor_type']);
        }

        if (! empty($filters['billing_cycle'])) {
            $query->whereIn('billing_cycle', (array) $filters['billing_cycle']);
        }

        if (! empty($filters['currency'])) {
            $query->whereIn('currency', (array) $filters['currency']);
        }

        if (isset($filters['monitoring_enabled']) && $filters['monitoring_enabled'] !== '') {
            $query->where('monitoring_enabled', filter_var($filters['monitoring_enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false);
        }

        if (isset($filters['auto_detect_invoices']) && $filters['auto_detect_invoices'] !== '') {
            $query->where('auto_detect_invoices', filter_var($filters['auto_detect_invoices'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false);
        }

        if (isset($filters['auto_fetch_email']) && $filters['auto_fetch_email'] !== '') {
            $query->where('auto_fetch_email', filter_var($filters['auto_fetch_email'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false);
        }

        if (! empty($filters['renewal_from'])) {
            $query->whereDate('renewal_date', '>=', $filters['renewal_from']);
        }

        if (! empty($filters['renewal_to'])) {
            $query->whereDate('renewal_date', '<=', $filters['renewal_to']);
        }

        $sortColumn = $filters['sort'] ?? 'created_at';
        $sortDirection = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'created_at', 'updated_at', 'name', 'renewal_date', 'expected_amount',
            'status', 'vendor_type', 'billing_cycle', 'currency',
        ];

        if (! in_array($sortColumn, $allowedSorts, true)) {
            $sortColumn = 'created_at';
        }

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage);
    }

    public function find(string $id, bool $withTrashed = false): ?Vendor
    {
        $q = Vendor::query()->whereKey($id);

        if ($withTrashed) {
            $q->withTrashed();
        }

        return $q->first();
    }

    public function findTrashed(string $id): ?Vendor
    {
        return Vendor::onlyTrashed()->whereKey($id)->first();
    }

    public function create(array $attributes): Vendor
    {
        return Vendor::query()->create($attributes);
    }

    public function update(Vendor $vendor, array $attributes): Vendor
    {
        $vendor->update($attributes);

        return $vendor->fresh();
    }

    public function delete(Vendor $vendor): bool
    {
        return (bool) $vendor->delete();
    }

    public function restore(Vendor $vendor): bool
    {
        if (! $vendor->trashed()) {
            return false;
        }

        $vendor->restore();

        return true;
    }
}
