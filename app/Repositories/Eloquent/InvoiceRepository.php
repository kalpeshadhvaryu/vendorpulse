<?php

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::query()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function find(string $id): ?Invoice
    {
        return Invoice::query()->whereKey($id)->first();
    }

    public function create(array $attributes): Invoice
    {
        return Invoice::query()->create($attributes);
    }

    public function update(Invoice $invoice, array $attributes): Invoice
    {
        $invoice->update($attributes);

        return $invoice->fresh();
    }

    public function delete(Invoice $invoice): bool
    {
        return (bool) $invoice->delete();
    }
}
