<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeContract;
use App\Models\Hr\EmployeeDocument;
use App\Models\Notification;
use App\Services\Hr\HrReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Contract-renewal and document-expiry reminders.
 *
 * The behaviour worth pinning is the quiet part: a reminder must fire once,
 * must not fire again the same day, and must not keep nagging forever about
 * something that already lapsed.
 */
class HrReminderFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();
        $this->employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
        ]);
    }

    private function reminders(): HrReminderService
    {
        return app(HrReminderService::class);
    }

    private function contract(int $expiresInDays): EmployeeContract
    {
        return EmployeeContract::factory()->expiringInDays($expiresInDays)->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->ctx['branch']->id,
        ]);
    }

    private function document(int $expiresInDays): EmployeeDocument
    {
        return EmployeeDocument::factory()->expiringInDays($expiresInDays)->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->ctx['branch']->id,
        ]);
    }

    public function test_a_contract_inside_its_reminder_window_notifies_once(): void
    {
        $contract = $this->contract(10);

        $this->assertSame(1, $this->reminders()->runContractExpiryCheck());

        $this->assertSame(1, Notification::where('type', 'contract_expiring')->count());
        $this->assertNotNull($contract->fresh()->last_reminded_at);
    }

    public function test_running_twice_in_a_day_does_not_notify_twice(): void
    {
        $this->contract(10);

        $this->reminders()->runContractExpiryCheck();
        $second = $this->reminders()->runContractExpiryCheck();

        $this->assertSame(0, $second);
        $this->assertSame(1, Notification::where('type', 'contract_expiring')->count());
    }

    public function test_a_contract_outside_its_window_is_left_alone(): void
    {
        // Well beyond the 30-day default.
        $this->contract(200);

        $this->assertSame(0, $this->reminders()->runContractExpiryCheck());
        $this->assertSame(0, Notification::where('type', 'contract_expiring')->count());
    }

    /**
     * An already-lapsed contract needs a report, not a notification that
     * repeats every morning forever.
     */
    public function test_an_already_expired_contract_does_not_notify(): void
    {
        $this->contract(-5);

        $this->assertSame(0, $this->reminders()->runContractExpiryCheck());
    }

    public function test_a_permanent_contract_with_no_end_date_never_notifies(): void
    {
        EmployeeContract::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->ctx['branch']->id,
            'contract_type' => 'permanent',
            'end_date' => null,
        ]);

        $this->assertSame(0, $this->reminders()->runContractExpiryCheck());
    }

    public function test_an_expiring_document_notifies_once(): void
    {
        $document = $this->document(14);

        $this->assertSame(1, $this->reminders()->runDocumentExpiryCheck());

        $this->assertSame(1, Notification::where('type', 'document_expiring')->count());
        $this->assertNotNull($document->fresh()->last_reminded_at);
    }

    public function test_an_expired_document_does_not_notify(): void
    {
        $this->document(-3);

        $this->assertSame(0, $this->reminders()->runDocumentExpiryCheck());
    }

    /**
     * A record reminded yesterday becomes eligible again today, so a contract
     * running down to its end date keeps being surfaced rather than being
     * announced once and forgotten.
     *
     * Time is actually travelled rather than passing a different $asOf: both
     * the last_reminded_at stamp and NotificationService's per-day dedupe key
     * read the real clock, so only moving the clock exercises the real path.
     */
    public function test_a_record_reminded_yesterday_notifies_again_the_next_day(): void
    {
        $this->contract(10);

        $this->reminders()->runContractExpiryCheck();
        $this->assertSame(1, Notification::where('type', 'contract_expiring')->count());

        $this->travel(1)->day();

        $this->assertSame(1, $this->reminders()->runContractExpiryCheck());
        $this->assertSame(2, Notification::where('type', 'contract_expiring')->count());
    }

    public function test_the_command_runs_both_checks(): void
    {
        $this->contract(10);
        $this->document(10);

        $this->artisan('hr:reminders')
            ->expectsOutputToContain('Contract renewal reminders sent: 1')
            ->expectsOutputToContain('Document expiry reminders sent: 1')
            ->assertSuccessful();
    }

    public function test_the_command_rejects_an_unknown_type(): void
    {
        $this->artisan('hr:reminders', ['--type' => 'nonsense'])->assertFailed();
    }
}
