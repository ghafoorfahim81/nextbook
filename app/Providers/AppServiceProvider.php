<?php

namespace App\Providers;

use App\Models\Hr\Employee;
use App\Models\Transaction\Transaction;
use App\Observers\Hr\EmployeeObserver;
use App\Observers\TransactionObserver;
use App\Observers\ModelActivityObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Transaction::observe(TransactionObserver::class);

        Relation::enforceMorphMap([
            'user' => 'App\Models\User',
            'role' => 'App\Models\Role',
            'permission' => 'App\Models\Permission',
            'account' => 'App\Models\Account\Account',
            'ledger' => 'App\Models\Ledger\Ledger',
            'ledger_opening' => 'App\Models\Ledger\LedgerOpening',
            'ledger_transaction' => 'App\Models\Ledger\LedgerTransaction',
            'purchase' => 'App\Models\Purchase\Purchase',
            'sale' => 'App\Models\Sale\Sale',
            'expense' => 'App\Models\Expense\Expense',
            'income' => 'App\Models\Income\Income',
            'transfer' => 'App\Models\Transfer\Transfer',
            'item_transfer' => 'App\Models\ItemTransfer\ItemTransfer',
            'stock_adjustment' => 'App\Models\Adjustment\Adjustment',
            'opening' => 'App\Models\Inventory\StockOpening',
            'stock_out' => 'App\Models\Inventory\StockOut',
            'item' => 'App\Models\Inventory\Item',
            'owner' => 'App\Models\Owner\Owner',
            'drawing' => 'App\Models\Owner\Drawing',
            'journal_entry' => 'App\Models\JournalEntry\JournalEntry',
            'landed_cost' => 'App\Models\Inventory\LandedCost',
            // Human resources
            'employee' => 'App\Models\Hr\Employee',
            'employee_contract' => 'App\Models\Hr\EmployeeContract',
            'employee_document' => 'App\Models\Hr\EmployeeDocument',
            'shift' => 'App\Models\Hr\Shift',
            'holiday' => 'App\Models\Hr\Holiday',
            'attendance' => 'App\Models\Hr\Attendance',
            'attendance_device' => 'App\Models\Hr\AttendanceDevice',
            'leave_type' => 'App\Models\Hr\LeaveType',
            'leave_allocation' => 'App\Models\Hr\LeaveAllocation',
            'leave_request' => 'App\Models\Hr\LeaveRequest',
            'salary_component' => 'App\Models\Hr\SalaryComponent',
            'salary_structure' => 'App\Models\Hr\SalaryStructure',
            'tax_bracket_set' => 'App\Models\Hr\TaxBracketSet',
            'payroll' => 'App\Models\Hr\Payroll',
            'payroll_line' => 'App\Models\Hr\PayrollLine',
            'salary_payment' => 'App\Models\Hr\SalaryPayment',
            'employee_loan' => 'App\Models\Hr\EmployeeLoan',
            'job_opening' => 'App\Models\Hr\JobOpening',
            'job_application' => 'App\Models\Hr\JobApplication',
            'interview' => 'App\Models\Hr\Interview',

        ]);

        // Registered explicitly rather than through the activity_log list: this
        // observer maintains the employee's companion ledger, which is domain
        // behaviour and must run even where audit logging is switched off.
        Employee::observe(EmployeeObserver::class);

        foreach (config('activity_log.observer.models', []) as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(ModelActivityObserver::class);
            }
        }
    }
}
