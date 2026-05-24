<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmailLogService
{
    /**
     * @param  array{search?: string, vendor_id?: string, processing_status?: string}  $filters
     */
    public function paginate(int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        $query = EmailLog::query()
            ->with([
                'vendor:id,name',
                'mailbox:id,name',
                'invoiceExtractions' => fn ($q) => $q->latest('created_at')->limit(1),
            ])
            ->orderByDesc('received_at')
            ->orderByDesc('created_at');

        if (! empty($filters['vendor_id'])) {
            $query->where('vendor_id', (string) $filters['vendor_id']);
        }

        if (! empty($filters['processing_status'])) {
            $query->where('processing_status', (string) $filters['processing_status']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('subject', 'like', $term)
                    ->orWhere('from_email', 'like', $term)
                    ->orWhere('external_message_id', 'like', $term);
            });
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): ?EmailLog
    {
        return EmailLog::query()
            ->with([
                'vendor:id,name',
                'mailbox:id,name',
                'invoiceExtractions' => fn ($q) => $q->orderByDesc('created_at'),
            ])
            ->whereKey($id)
            ->first();
    }
}
