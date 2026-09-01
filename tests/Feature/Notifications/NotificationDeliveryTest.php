<?php

namespace Tests\Feature\Notifications;

use App\Enums\SalePurchaseType;
use App\Enums\TransactionStatus;
use App\Enums\UserStatus;
use App\Jobs\SendNotificationEmailJob;
use App\Models\Administration\Branch;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Sale\Sale;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_new_transaction_alert_does_not_reach_super_admins_of_other_branches(): void
    {
        $ownBranchAdmin = $this->makeSuperAdmin($this->ctx['branch']->id);
        $otherBranch = Branch::factory()->create(['name' => 'Other Branch', 'is_main' => false]);
        $otherBranchAdmin = $this->makeSuperAdmin($otherBranch->id);

        $transaction = $this->makePostedTransaction($this->ctx['branch']->id);

        app(NotificationService::class)->notifySuperAdminsOfNewTransaction($transaction);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $ownBranchAdmin->id,
            'type' => 'new_transaction',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $otherBranchAdmin->id,
            'type' => 'new_transaction',
        ]);
    }

    public function test_observer_still_fires_for_a_posted_transaction_after_commit(): void
    {
        $admin = $this->makeSuperAdmin($this->ctx['branch']->id);

        // The observer now implements ShouldHandleEventsAfterCommit; this guards
        // against the deferral quietly swallowing the notification entirely.
        Transaction::create([
            'currency_id' => $this->ctx['currency']->id,
            'base_currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'is_cross_currency' => false,
            'date' => now()->toDateString(),
            'voucher_number' => 'OBS-001',
            'status' => TransactionStatus::POSTED->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'new_transaction',
        ]);
    }

    public function test_draft_transactions_do_not_raise_a_new_transaction_alert(): void
    {
        $admin = $this->makeSuperAdmin($this->ctx['branch']->id);

        Transaction::create([
            'currency_id' => $this->ctx['currency']->id,
            'base_currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'is_cross_currency' => false,
            'date' => now()->toDateString(),
            'voucher_number' => 'OBS-002',
            'status' => TransactionStatus::DRAFT->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin->id,
            'type' => 'new_transaction',
        ]);
    }

    public function test_new_transaction_alert_is_skipped_when_the_super_admin_role_is_missing(): void
    {
        Role::query()->where('slug', 'super-admin')->delete();

        $transaction = $this->makePostedTransaction($this->ctx['branch']->id);

        // Previously this threw Spatie's RoleDoesNotExist, which surfaced as a
        // hard failure while posting the voucher.
        app(NotificationService::class)->notifySuperAdminsOfNewTransaction($transaction);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_duplicate_notifications_are_suppressed_by_the_dedupe_key(): void
    {
        $user = $this->makeSuperAdmin($this->ctx['branch']->id);
        $service = app(NotificationService::class);

        $first = $service->notifyUser(
            user: $user,
            type: 'low_stock',
            title: 'Low Stock Alert',
            message: 'Item A is below minimum stock.',
            dedupeKey: 'low-stock:item-a',
        );

        $second = $service->notifyUser(
            user: $user,
            type: 'low_stock',
            title: 'Low Stock Alert',
            message: 'Item A is below minimum stock.',
            dedupeKey: 'low-stock:item-a',
        );

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->count());
        $this->assertSame('low-stock:item-a', $first->dedupe_key);
    }

    public function test_notification_email_is_queued_rather_than_sent_inline(): void
    {
        Queue::fake();

        $user = $this->makeSuperAdmin($this->ctx['branch']->id);

        app(NotificationService::class)->notifyUser(
            user: $user,
            type: 'low_stock',
            title: 'Low Stock Alert',
            message: 'Item A is below minimum stock.',
            dedupeKey: 'low-stock:queued',
        );

        Queue::assertPushed(SendNotificationEmailJob::class, 1);
    }

    public function test_leave_notifications_respect_user_preferences(): void
    {
        $user = $this->makeSuperAdmin($this->ctx['branch']->id);
        $user->setPreference('notifications.leave_status_alert', false);
        $user->save();

        $result = app(NotificationService::class)->notifyUser(
            user: $user,
            type: 'leave_approved',
            title: 'Leave Approved',
            message: 'Your leave was approved.',
            dedupeKey: 'leave_approved:1',
        );

        // These types were unmapped, so the preference was ignored entirely.
        $this->assertNull($result);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_an_overdue_sale_raises_one_notification_not_two_for_a_user_subscribed_to_both(): void
    {
        $user = $this->makeSuperAdmin($this->ctx['branch']->id);
        $user->setPreference('notifications.overdue_sale_alert', true);
        $user->setPreference('notifications.overdue_invoice_alert', true);
        $user->save();

        $this->makeOverdueSale();

        app(NotificationService::class)->runOverdueChecks();

        $notifications = Notification::query()->where('user_id', $user->id)->get();

        // `overdue_sale` and `overdue_invoice` ran off identical filters, so this
        // used to produce one of each for the same sale, every day.
        $this->assertCount(1, $notifications);
        $this->assertSame('overdue_sale', $notifications->first()->type);
    }

    public function test_a_user_subscribed_only_to_overdue_invoices_still_gets_one(): void
    {
        $user = $this->makeSuperAdmin($this->ctx['branch']->id);
        $user->setPreference('notifications.overdue_sale_alert', false);
        $user->setPreference('notifications.overdue_invoice_alert', true);
        $user->save();

        $this->makeOverdueSale();

        app(NotificationService::class)->runOverdueChecks();

        $notifications = Notification::query()->where('user_id', $user->id)->get();

        $this->assertCount(1, $notifications);
        $this->assertSame('overdue_invoice', $notifications->first()->type);
    }

    public function test_marking_another_users_notification_as_read_is_forbidden(): void
    {
        $other = $this->makeSuperAdmin($this->ctx['branch']->id);

        $notification = Notification::create([
            'user_id' => $other->id,
            'type' => 'low_stock',
            'dedupe_key' => 'low-stock:foreign',
            'title' => 'Low Stock Alert',
            'message' => 'Not yours.',
            'is_read' => false,
            'data' => [],
        ]);

        $this->actingAs($this->ctx['user'])
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_prune_drops_stale_notifications_and_keeps_current_ones(): void
    {
        $user = $this->ctx['user'];

        $staleRead = $this->makeNotification($user, 'stale-read', isRead: true, ageInDays: 120);
        $staleUnread = $this->makeNotification($user, 'stale-unread', isRead: false, ageInDays: 400);
        $recentRead = $this->makeNotification($user, 'recent-read', isRead: true, ageInDays: 10);
        $oldishUnread = $this->makeNotification($user, 'oldish-unread', isRead: false, ageInDays: 120);

        app(NotificationService::class)->pruneOldNotifications();

        $this->assertNull(Notification::find($staleRead->id));
        $this->assertNull(Notification::find($staleUnread->id));
        $this->assertNotNull(Notification::find($recentRead->id));
        // Unread items survive until the hard one-year cutoff.
        $this->assertNotNull(Notification::find($oldishUnread->id));
    }

    private function makeSuperAdmin(string $branchId): User
    {
        $preferences = User::DEFAULT_PREFERENCES;
        $preferences['notifications']['new_transaction_alert'] = true;

        $user = User::factory()->create([
            'branch_id' => $branchId,
            'company_id' => $this->ctx['company']->id,
            'status' => UserStatus::ACTIVE->value,
            'preferences' => $preferences,
        ]);

        $role = Role::query()->firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web'],
            ['slug' => 'super-admin']
        );

        $user->assignRole($role);

        return $user->fresh();
    }

    private function makePostedTransaction(string $branchId, array $overrides = []): Transaction
    {
        return Transaction::withoutEvents(fn () => Transaction::create([
            'currency_id' => $this->ctx['currency']->id,
            'base_currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'is_cross_currency' => false,
            'date' => now()->toDateString(),
            'voucher_number' => 'TEST-001',
            'status' => TransactionStatus::POSTED->value,
            'branch_id' => $branchId,
            'created_by' => $this->ctx['user']->id,
            ...$overrides,
        ]));
    }

    /**
     * A posted sale, past its due date, with an untouched receivable behind it.
     */
    private function makeOverdueSale(): Sale
    {
        // branch_id is set by a model event that withoutEvents() suppresses, so
        // force it on explicitly.
        $sale = Sale::withoutEvents(function () {
            $sale = new Sale;
            $sale->forceFill([
                'number' => 1001,
                'customer_id' => $this->ctx['customer_ledger']->id,
                'date' => now()->subDays(60)->toDateString(),
                'due_date' => now()->subDays(30)->toDateString(),
                'type' => SalePurchaseType::Credit->value,
                'status' => TransactionStatus::POSTED->value,
                'branch_id' => $this->ctx['branch']->id,
                'created_by' => $this->ctx['user']->id,
            ])->save();

            return $sale;
        });

        $transaction = $this->makePostedTransaction($this->ctx['branch']->id, [
            'voucher_number' => 'SALE-1001',
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'date' => now()->subDays(60)->toDateString(),
        ]);

        TransactionLine::withoutEvents(fn () => TransactionLine::create([
            'transaction_id' => $transaction->id,
            'account_id' => $this->ctx['accounts']['account-receivable']->id,
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'debit' => 5000,
            'credit' => 0,
            'base_debit' => 5000,
            'base_credit' => 0,
        ]));

        return $sale;
    }

    private function makeNotification(User $user, string $key, bool $isRead, int $ageInDays): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'low_stock',
            'dedupe_key' => $key,
            'title' => 'Low Stock Alert',
            'message' => $key,
            'is_read' => $isRead,
            'data' => [],
        ]);

        $notification->forceFill(['created_at' => now()->subDays($ageInDays)])->saveQuietly();

        return $notification->fresh();
    }
}
