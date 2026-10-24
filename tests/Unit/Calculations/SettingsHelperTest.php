<?php

namespace Tests\Unit\Calculations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class SettingsHelperTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    public function test_helper_reads_defaults_when_user_is_not_authenticated(): void
    {
        auth()->logout();

        $this->assertEquals('with_nature', balanceNatureFormat());
        $this->assertEquals(15, recordsPerPage());
        $this->assertEquals(\App\Support\Settings::defaults(), all_user_preferences());
    }

    public function test_helper_can_set_and_get_user_preferences(): void
    {
        $ctx = $this->bootstrapErpContext();

        $result = set_user_preference('appearance.records_per_page', 50, $ctx['user']);

        $this->assertTrue($result);
        $this->assertEquals(50, user_preference('appearance.records_per_page', 15, $ctx['user']));
        $this->assertEquals(50, recordsPerPage());
    }

    public function test_balance_nature_format_is_the_companys_setting(): void
    {
        $ctx = $this->bootstrapErpContext();
        $this->actingAs($ctx['user']);

        // A user's own copy no longer counts (phase 6); the company's does.
        $ctx['user']->setPreference('appearance.balance_nature_format', 'without_nature')->save();
        $this->assertEquals('with_nature', balanceNatureFormat());

        set_company_setting('appearance.balance_nature_format', 'without_nature');
        $this->assertEquals('without_nature', balanceNatureFormat());
    }
}
