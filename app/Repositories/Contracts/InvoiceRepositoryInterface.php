<?php

namespace App\Repositories\Contracts;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(string $id): ?Invoice;

    public function create(array $attributes): Invoice;

    public function update(Invoice $invoice, array $attributes): Invoice;

    public function delete(Invoice $invoice): bool;
}
