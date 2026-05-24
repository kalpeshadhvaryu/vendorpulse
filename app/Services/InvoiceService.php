<?php

namespace App\Services;

use App\EmailMonitoring\Support\EmailLogInvoiceOutcome;
use App\Models\EmailLog;
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
        $paginator = $this->invoices->paginate($perPage);
        $this->attachEmailSources($paginator->items());

        return $paginator;
    }

    public function find(string $id): ?Invoice
    {
        $invoice = $this->invoices->find($id);
        if ($invoice) {
            $this->attachEmailSources([$invoice]);
        }

        return $invoice;
    }

    /**
     * @param  array<int, Invoice>  $invoices
     */
    private function attachEmailSources(array $invoices): void
    {
        $logIds = [];
        foreach ($invoices as $invoice) {
            $ref = EmailLogInvoiceOutcome::invoiceEmailSourceFromMetadata(
                is_array($invoice->metadata) ? $invoice->metadata : null
            );
            if ($ref) {
                $logIds[] = $ref['email_log_id'];
            }
        }

        if ($logIds === []) {
            return;
        }

        $logs = EmailLog::query()
            ->whereIn('id', array_values(array_unique($logIds)))
            ->get(['id', 'subject', 'from_email', 'received_at'])
            ->keyBy('id');

        foreach ($invoices as $invoice) {
            $base = EmailLogInvoiceOutcome::invoiceEmailSourceFromMetadata(
                is_array($invoice->metadata) ? $invoice->metadata : null
            );
            if (! $base) {
                continue;
            }

            $log = $logs->get($base['email_log_id']);
            $invoice->setAttribute('email_source', array_merge($base, [
                'subject' => $log?->subject,
                'from_email' => $log?->from_email,
                'received_at' => $log?->received_at?->toIso8601String(),
            ]));
        }
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
