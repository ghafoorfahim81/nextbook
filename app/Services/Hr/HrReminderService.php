<?php

namespace App\Services\Hr;

use App\Models\Hr\EmployeeContract;
use App\Models\Hr\EmployeeDocument;
use App\Models\User;
use App\Services\DateConversionService;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Contract-renewal and document-expiry reminders.
 *
 * Both scans run per branch and notify the HR-facing users of that branch,
 * mirroring how NotificationService's other batch checks work. `last_reminded_at`
 * is stamped after a successful pass so a record is announced once per day at
 * most, even if the scheduler double-fires.
 */
class HrReminderService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly DateConversionService $dates,
    ) {
    }

    /**
     * @return int number of records that triggered a notification
     */
    public function runContractExpiryCheck(?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? Carbon::today();
        $sent = 0;

        foreach ($this->recipientsByBranch('notifications.contract_expiry_alert') as $branchId => $users) {
            $contracts = EmployeeContract::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->with('employee:id,full_name,code')
                ->dueForRenewalReminder($asOf)
                ->get();

            foreach ($contracts as $contract) {
                $notified = false;

                foreach ($users as $user) {
                    $result = $this->notifications->notifyUser(
                        user: $user,
                        type: 'contract_expiring',
                        title: __('hr.notifications.contract_expiring_title'),
                        message: __('hr.notifications.contract_expiring_body', [
                            'employee' => $contract->employee?->full_name ?? '',
                            'number' => $contract->contract_number,
                            'date' => $this->dates->toDisplay($contract->end_date),
                        ]),
                        data: [
                            'employee_id' => $contract->employee_id,
                            'contract_id' => $contract->id,
                            'branch_id' => $branchId,
                            'days_until_expiry' => $contract->daysUntilExpiry($asOf),
                        ],
                        dedupeKey: 'contract-expiry:'.$contract->id,
                    );

                    $notified = $notified || $result !== null;
                }

                // Stamped only when someone was actually told. Otherwise a run
                // with no eligible recipients would mark the contract as
                // handled and silence it for good.
                if ($notified) {
                    $contract->forceFill(['last_reminded_at' => now()])->saveQuietly();
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /**
     * @return int number of records that triggered a notification
     */
    public function runDocumentExpiryCheck(?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? Carbon::today();
        $sent = 0;

        foreach ($this->recipientsByBranch('notifications.document_expiry_alert') as $branchId => $users) {
            $documents = EmployeeDocument::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->with('employee:id,full_name,code')
                ->dueForExpiryReminder($asOf)
                ->get();

            foreach ($documents as $document) {
                $notified = false;

                foreach ($users as $user) {
                    $result = $this->notifications->notifyUser(
                        user: $user,
                        type: 'document_expiring',
                        title: __('hr.notifications.document_expiring_title'),
                        message: __('hr.notifications.document_expiring_body', [
                            'employee' => $document->employee?->full_name ?? '',
                            'type' => $document->document_type?->getLabel() ?? '',
                            'date' => $this->dates->toDisplay($document->expiry_date),
                        ]),
                        data: [
                            'employee_id' => $document->employee_id,
                            'document_id' => $document->id,
                            'branch_id' => $branchId,
                            'days_until_expiry' => $document->daysUntilExpiry($asOf),
                        ],
                        dedupeKey: 'document-expiry:'.$document->id,
                    );

                    $notified = $notified || $result !== null;
                }

                if ($notified) {
                    $document->forceFill(['last_reminded_at' => now()])->saveQuietly();
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /**
     * Active users grouped by branch who have opted into this alert.
     *
     * Deliberately not restricted to a permission: NotificationService's other
     * checks work the same way, and a user's own preference is the intended
     * opt-out.
     */
    private function recipientsByBranch(string $preferenceKey): Collection
    {
        return User::query()
            ->whereNull('deleted_at')
            ->whereNotNull('branch_id')
            ->get()
            ->filter(fn (User $user) => (bool) $user->getPreference($preferenceKey, true))
            ->groupBy('branch_id');
    }
}
