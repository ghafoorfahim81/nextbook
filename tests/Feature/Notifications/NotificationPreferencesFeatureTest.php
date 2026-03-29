<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class NotificationPreferencesFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_notification_settings_are_saved_through_preferences_endpoint(): void
    {
        $response = $this->from(route('preferences.index'))
            ->put(route('preferences.update'), [
                'notifications' => [
                    'email_notifications' => false,
                    'low_balance_alert' => true,
                    'overdue_invoice_alert' => false,
                    'new_transaction_alert' => true,
                    'daily_summary_report' => true,
                    'weekly_financial_summary' => false,
                ],
            ]);

        $response->assertRedirect(route('preferences.index'));

        $user = User::query()->findOrFail($this->ctx['user']->id);
        $this->assertEquals(false, data_get($user->preferences, 'notifications.email_notifications'));
        $this->assertEquals(true, data_get($user->preferences, 'notifications.new_transaction_alert'));
        $this->assertEquals(true, data_get($user->preferences, 'notifications.daily_summary_report'));
    }

    public function test_repeating_same_notification_payload_is_idempotent_and_does_not_duplicate_state(): void
    {
        $payload = [
            'notifications' => [
                'email_notifications' => true,
                'low_balance_alert' => true,
                'overdue_invoice_alert' => true,
                'new_transaction_alert' => false,
                'daily_summary_report' => false,
                'weekly_financial_summary' => false,
            ],
        ];

        $this->put(route('preferences.update'), $payload)->assertRedirect();
        $this->put(route('preferences.update'), $payload)->assertRedirect();

        $user = User::query()->findOrFail($this->ctx['user']->id);
        $merged = $user->getAllPreferences();

        $this->assertEquals($payload['notifications'], $merged['notifications']);
    }
}
