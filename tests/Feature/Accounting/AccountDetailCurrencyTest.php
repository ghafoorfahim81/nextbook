<?php

namespace Tests\Feature\Accounting;

use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;
use ZipArchive;

class AccountDetailCurrencyTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Currency $usd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();

        $this->usd = Currency::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'exchange_rate' => 65.5,
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => 'us.png',
        ]);
    }

    private function cashAccount(string $name, string $number): Account
    {
        return Account::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => $name,
            'local_name' => $name,
            'number' => $number,
            'slug' => str($name)->slug()->value(),
            'account_type_id' => $this->ctx['account_types']['cash-or-bank']->id,
        ]);
    }

    private function postOpening(Account $account, Currency $currency, float $rate, float $amount, string $date = '2026-03-01')
    {
        return app(TransactionService::class)->post(
            header: [
                'currency_id' => $currency->id,
                'rate' => $rate,
                'date' => $date,
                'reference_type' => Account::class,
                'reference_id' => $account->id,
                'remark' => 'Opening balance for account ' . $account->name,
            ],
            lines: [
                [
                    'account_id' => $account->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'remark' => 'Opening balance',
                    'remark_fa' => 'موجودی اولیه',
                    'remark_ps' => 'د پرانیستلو بیلانس',
                ],
                [
                    'account_id' => $this->ctx['accounts']['opening-balance-equity']->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'remark' => 'Opening balance',
                ],
            ],
        );
    }

    public function test_account_detail_reports_the_balance_in_the_account_own_currency(): void
    {
        $account = $this->cashAccount('Cash In Hand USD', '1002');
        $this->postOpening($account, $this->usd, 65.5, 1000);

        $response = $this->getJson(route('chart-of-accounts.show', ['chart_of_account' => $account->id]));

        $response->assertOk();
        $response->assertJsonCount(1, 'currencyBalances');
        $response->assertJsonPath('currencyBalances.0.currency_code', 'USD');
        $response->assertJsonPath('currencyBalances.0.total_debit', $this->amount(1000));
        $response->assertJsonPath('currencyBalances.0.total_credit', $this->amount(0));
        $response->assertJsonPath('currencyBalances.0.balance', $this->amount(1000));
        $response->assertJsonPath('currencyBalances.0.balance_nature', 'dr');
        // The converted figure stays available, but only as a footnote.
        $response->assertJsonPath('currencyBalances.0.home_equivalent', $this->amount(65500));
    }

    public function test_account_detail_keeps_each_currency_separate(): void
    {
        $account = $this->cashAccount('Mixed Cash', '1003');
        $this->postOpening($account, $this->usd, 65.5, 1000);
        $this->postOpening($account, $this->ctx['currency'], 1, 2000);

        $response = $this->getJson(route('chart-of-accounts.show', ['chart_of_account' => $account->id]));

        $response->assertOk();
        $response->assertJsonCount(2, 'currencyBalances');
        // Home currency first, then the rest by code.
        $response->assertJsonPath('currencyBalances.0.currency_code', 'AFN');
        $response->assertJsonPath('currencyBalances.0.balance', $this->amount(2000));
        $response->assertJsonPath('currencyBalances.1.currency_code', 'USD');
        $response->assertJsonPath('currencyBalances.1.balance', $this->amount(1000));
    }

    /**
     * JSON drops the decimal on whole amounts, so compare numerically rather
     * than by type.
     */
    private function amount(float $expected): \Closure
    {
        return fn ($value) => is_numeric($value) && (float) $value === $expected;
    }

    public function test_account_detail_totals_foreign_positions_into_the_account_currency(): void
    {
        // Rate has moved since the account was opened at 65.5.
        $this->usd->update(['exchange_rate' => 65]);

        $account = $this->cashAccount('Cash In Hand USD Total', '1006');
        $opening = $this->postOpening($account, $this->usd, 65.5, 1000);
        $account->opening()->create(['transaction_id' => $opening->id]);

        // A receipt entered in AFN that landed in the USD drawer: 13,000 AFN at
        // the current 65 rate is 200 USD.
        $this->postOpening($account, $this->ctx['currency'], 1, 13000, '2026-03-05');

        $response = $this->getJson(route('chart-of-accounts.show', ['chart_of_account' => $account->id]));

        $response->assertOk();
        $response->assertJsonPath('convertedBalance.currency_code', 'USD');
        $response->assertJsonPath('convertedBalance.amount', $this->amount(1200));
        $response->assertJsonPath('convertedBalance.balance_nature', 'dr');

        // The per-currency positions are untouched by the conversion.
        $response->assertJsonPath('currencyBalances.0.currency_code', 'AFN');
        $response->assertJsonPath('currencyBalances.0.balance', $this->amount(13000));
        $response->assertJsonPath('currencyBalances.1.currency_code', 'USD');
        $response->assertJsonPath('currencyBalances.1.balance', $this->amount(1000));
    }

    public function test_a_single_currency_account_converts_to_its_own_balance(): void
    {
        $this->usd->update(['exchange_rate' => 65]);

        $account = $this->cashAccount('Cash In Hand USD Only', '1007');
        $opening = $this->postOpening($account, $this->usd, 65.5, 1000);
        $account->opening()->create(['transaction_id' => $opening->id]);

        $response = $this->getJson(route('chart-of-accounts.show', ['chart_of_account' => $account->id]));

        $response->assertOk();
        // Taken as it stands, not round-tripped through the moved rate.
        $response->assertJsonPath('convertedBalance.amount', $this->amount(1000));
    }

    public function test_transaction_export_keeps_amounts_in_the_transaction_currency(): void
    {
        $account = $this->cashAccount('Cash In Hand USD Export', '1004');
        $this->postOpening($account, $this->usd, 65.5, 1000);

        $sheet = $this->exportedSheetXml($account);

        $this->assertStringContainsString('<v>1000</v>', $sheet);
        $this->assertStringNotContainsString('65500', $sheet);
    }

    public function test_transaction_export_uses_the_remark_of_the_active_locale(): void
    {
        $this->ctx['user']->update(['locale' => 'fa']);
        $account = $this->cashAccount('Cash In Hand Persian', '1005');
        $this->postOpening($account, $this->usd, 65.5, 1000);

        $sheet = $this->exportedSheetXml($account);

        $this->assertStringContainsString('موجودی اولیه', $sheet);
        $this->assertStringNotContainsString('Opening balance', $sheet);
        // The header carries the account name next to the sheet title.
        $this->assertStringContainsString('Cash In Hand Persian', $sheet);
    }

    private function exportedSheetXml(Account $account): string
    {
        $response = $this->get(route('chart-of-accounts.export-transactions', [
            'chart_of_account' => $account->id,
        ]));

        $response->assertOk();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        return $sheet;
    }
}
