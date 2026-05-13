<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceService
{
    public function __construct(
        protected InvoiceRepositoryInterface $invoices,
        protected CurrentOrganization $currentOrganization
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->invoices->paginate($perPage);
    }

    public function find(string $id): ?Invoice
    {
        return $this->invoices->find($id);
    }

    public function create(array $data, User $actor): Invoice
    {
        $data['organization_id'] = $this->currentOrganization->id();
        $data['created_by'] = $actor->id;
        $data['updated_by'] = $actor->id;

        return $this->invoices->create($data);
    }

    public function update(Invoice $invoice, array $data, User $actor): Invoice
    {
        $data['updated_by'] = $actor->id;

        return $this->invoices->update($invoice, $data);
    }

    public function delete(Invoice $invoice): bool
    {
        return $this->invoices->delete($invoice);
    }
}
