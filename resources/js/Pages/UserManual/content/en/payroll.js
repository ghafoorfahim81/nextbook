export default {
    id: 'payroll',
    number: '11',
    title: 'Payroll guide',
    subtitle: 'Components & structure · tax table · run stages · calculation order · accounting entry · payment · loans',
    summary:
        'The payroll module calculates each employee’s salary from their salary structure, attendance, and leave, withholds tax, posts the result to the accounting ledger, and manages payment.',
    chapters: [
        {
            id: 'four-settings',
            number: '1',
            title: 'The four payroll settings',
            blocks: [
                {
                    type: 'table',
                    headers: ['Setting', 'Its role'],
                    rows: [
                        ['Components', 'The definition of every line that can appear in a salary: basic pay, transport allowance, lateness deduction, etc. A general definition, not one person’s amount.'],
                        ['Structure', 'One specific employee’s salary package: their basic pay plus the components that apply to them.'],
                        ['Tax table', 'The salary tax brackets that decide what percentage of tax is withheld at each income level.'],
                        ['Payroll run', 'The operation of calculating payroll for a specific month and a group of employees.'],
                    ],
                },
                {
                    type: 'flow',
                    steps: ['Components & structure', 'Tax table', 'Payroll run', 'Calculate', 'Post to ledger', 'Pay'],
                },
            ],
        },
        {
            id: 'components',
            number: '2',
            title: 'Salary components',
            blocks: [
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Component type', 'Earning (added to salary), deduction (subtracted), or employer contribution (a company cost that is not deducted from the employee’s salary).'],
                        ['Calculation method', 'Fixed amount, percentage of basic, percentage of gross, per day, or per hour.'],
                        ['Taxable', 'Whether this line is included in the tax calculation. Some allowances may be tax-exempt.'],
                        ['Affects gross', 'Whether this line counts toward the "gross salary" amount. Components calculated as a percentage of gross are based on this amount.'],
                        ['Prorated', 'If enabled and the employee did not work the whole month, this line is also reduced pro-rata. A transport allowance is usually prorated; a fixed bonus may not be.'],
                    ],
                },
                {
                    type: 'note',
                    label: 'System components',
                    text: 'Five components are used by the system itself and cannot be deleted: basic pay, overtime, unpaid leave, loan deduction, and salary tax. You can rename them, but their code is fixed — the payroll engine finds them by that code.',
                },
            ],
        },
        {
            id: 'structure',
            number: '3',
            title: 'Salary structure and effective date',
            blocks: [
                {
                    type: 'warn',
                    label: 'The golden rule',
                    text: 'To raise a salary, do not edit the existing structure. Create a new structure with a new effective date. Why: if you change the old structure and later have to recalculate a past month, the system applies the new salary and the numbers no longer match the payslip you already gave the employee. With a separate structure, each period finds the structure that was in force at the time.',
                },
                {
                    type: 'p',
                    text: 'A salary structure can be applied in three ways:',
                },
                {
                    type: 'list',
                    items: [
                        'To a specific employee — that employee’s personal package.',
                        'To a designation — a template applied to all employees of that designation.',
                        'To a department — a template for a whole department.',
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/payroll/structure.png',
                    caption: 'The salary structure form with components and effective date',
                    hint: 'screenshot of the salary structure',
                },
            ],
        },
        {
            id: 'tax-table',
            number: '4',
            title: 'The salary tax table',
            blocks: [
                {
                    type: 'p',
                    text: 'Salary tax in Afghanistan is calculated in brackets. The system default table:',
                },
                {
                    type: 'table',
                    headers: ['Bracket', 'From (AFN)', 'To (AFN)', 'Fixed amount', 'Marginal %'],
                    rows: [
                        ['1', '0', '5,000', '0', '0%'],
                        ['2', '5,000', '12,500', '0', '2%'],
                        ['3', '12,500', '100,000', '150', '10%'],
                        ['4', '100,000', 'no limit', '8,900', '20%'],
                    ],
                },
                {
                    type: 'formula',
                    text: 'Tax = bracket fixed amount + (income − bracket lower bound) × % ÷ 100',
                },
                {
                    type: 'p',
                    text: 'Example: a salary of 50,000 AFN falls in bracket 3: 150 + (50,000 − 12,500) × 10% = 150 + 3,750 = 3,900 AFN.',
                },
                { type: 'h4', text: 'Rules for building the tax table' },
                {
                    type: 'list',
                    items: [
                        'The first bracket must start at zero.',
                        'The last bracket must have no upper limit.',
                        'Each bracket must start exactly where the previous one ended.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Why are these rules strict?',
                    text: 'If there is a gap between two brackets, income that falls in that gap is in no bracket and its tax is calculated as zero without anyone noticing. If two brackets overlap, tax may be counted twice. Both produce payslips that look right but are wrong for a group of employees.',
                },
                {
                    type: 'tip',
                    label: 'Live check',
                    text: 'The tax table form has a section where you can enter an income amount and see how much tax the current brackets produce — before you save.',
                },
                {
                    type: 'p',
                    text: 'Each tax table has an effective date. When a period’s payroll is calculated, the system finds the table in force on the period end date and records its name on the payslip. So if the tax rate changes and you later recalculate an old month, the system reproduces the tax that was actually withheld.',
                },
            ],
        },
        {
            id: 'run-stages',
            number: '5',
            title: 'Payroll run stages',
            blocks: [
                {
                    type: 'flow',
                    steps: ['Draft', 'Calculated', 'Pending approval', 'Approved', 'Posted to ledger', 'Paid'],
                },
                {
                    type: 'table',
                    headers: ['Stage', 'What happens'],
                    rows: [
                        ['Draft', 'The run is created but not calculated. Dates and scope are editable.'],
                        ['Calculated', 'Each employee’s payslip is built. You can review the numbers and, if needed, fix attendance and recalculate. Nothing is posted to accounting yet.'],
                        ['Pending approval', 'Submitted to the manager.'],
                        ['Approved', 'The manager approved but it is not posted to the ledger yet.'],
                        ['Posted to ledger', 'The accounting entry is created. From this point the run is immutable and its attendance days lock.'],
                        ['Paid', 'All payslips are paid.'],
                        ['Reversed', 'The accounting entry is cancelled with a mirror entry. Attendance unlocks and you can re-run the period.'],
                    ],
                },
                {
                    type: 'note',
                    label: 'Why can’t you go straight from "calculated" to "posted"?',
                    text: 'Because approving payroll is a separate permission from preparing it. The person who prepares the numbers should not be the one who approves them. The system deliberately withholds this shortcut.',
                },
            ],
        },
        {
            id: 'calc-order',
            number: '6',
            title: 'Payroll calculation order',
            blocks: [
                {
                    type: 'p',
                    text: 'When you press "Calculate", the system runs these steps in this order for each employee:',
                },
                {
                    type: 'table',
                    headers: ['Step', 'Calculation', 'Explanation'],
                    rows: [
                        ['1', 'Basic pay', 'Taken from the salary structure in force for the period, reduced pro-rata to days worked if needed.'],
                        ['2', 'Fixed and percent-of-basic earnings', 'Allowances that are a fixed amount or a percentage of basic pay.'],
                        ['3', 'Overtime', 'Based on overtime hours recorded in attendance and the shift hourly rate.'],
                        ['4', 'Percent-of-gross earnings', 'The second calculation pass. These cannot be calculated until all other earnings are known, because they are based on the gross.'],
                        ['5', 'Deductions', 'Including the unpaid-leave deduction (at one working day’s rate).'],
                        ['6', 'Loan deduction', 'The instalment on the employee’s active loans.'],
                        ['7', 'Tax', 'Last. Based on taxable income, which requires all prior lines to be known.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Key point about the loan deduction',
                    text: 'The loan deduction reduces net pay but does not reduce taxable income. The logic is simple: repaying a loan is not a pay cut — the employee received the full salary and paid part of it back. So tax is calculated on their full salary.',
                },
            ],
        },
        {
            id: 'accounting-entry',
            number: '7',
            title: 'The payroll accounting entry',
            blocks: [
                {
                    type: 'p',
                    text: 'When payroll is posted to the ledger, an entry with this structure is created:',
                },
                {
                    type: 'table',
                    headers: ['Side', 'Account', 'Amount'],
                    rows: [
                        ['Debit', 'Salary expense (by employment type)', 'Total of basic pay and earnings'],
                        ['Debit', 'Allowances and commission', 'Total of allowances'],
                        ['Debit', 'Overtime expense', 'Total of overtime'],
                        ['Credit', 'Salaries payable (per employee)', 'Each employee’s net salary'],
                        ['Credit', 'Salary tax payable', 'Total tax withheld'],
                        ['Credit', 'Employee loans and advances', 'Total instalments withheld'],
                    ],
                },
                {
                    type: 'note',
                    label: 'Why is salaries payable recorded per employee?',
                    text: 'Expense lines are posted in aggregate so the entry does not get huge. But the "salaries payable" line is deliberately recorded per employee, because at payment time we must know exactly how much we owe each one. Also, withheld tax goes to a liability account, not an expense — the total salary was already recorded as an expense, and the withheld tax is money the company holds on the employee’s behalf to pay the Ministry of Finance.',
                },
            ],
        },
        {
            id: 'payment',
            number: '8',
            title: 'Paying the salary',
            blocks: [
                {
                    type: 'p',
                    text: 'After payroll is posted, the company owes each employee. Paying this is done from the "Pay payroll" menu.',
                },
                {
                    type: 'table',
                    headers: ['Case', 'What the system does'],
                    rows: [
                        ['Full payment', 'The whole payable is settled and the payslip becomes "paid".'],
                        ['Partial payment', 'Part is settled and the remainder stays in the payables list. The payslip becomes "partially paid".'],
                        ['Several months at once', 'If the amount exceeds one month, the system starts from the oldest month by default.'],
                        ['Manual month selection', 'To specify which month the money is for, enable "select payslips" and enter each month’s amount separately.'],
                        ['Overpayment', 'The excess is automatically moved to the "employee advances" account.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Salary in foreign currency',
                    text: 'If an employee’s salary is recorded in USD and the exchange rate changed between the posting date and the payment date, the system automatically records the difference as "currency exchange gain or loss".',
                },
            ],
        },
        {
            id: 'reversal',
            number: '9',
            title: 'Reversing payroll',
            blocks: [
                {
                    type: 'warn',
                    label: 'Reversing a payroll period',
                    text: 'If you find a mistake after posting to the ledger, reverse the period. A reversal: records a mirror entry in the ledger (the original stays), removes the loan instalments withheld in that period and rebuilds loan balances, and unlocks the attendance days. Condition: if even one payslip is partially paid, a reversal is not possible; cancel the payments first.',
                },
            ],
        },
        {
            id: 'loans',
            number: '10',
            title: 'Employee loans and advances',
            blocks: [
                {
                    type: 'table',
                    headers: ['Type', 'Meaning', 'Accounting account'],
                    rows: [
                        ['Salary advance', 'Next month’s salary paid early.', 'Employee advances'],
                        ['Advance', 'An amount given for a specific expense.', 'Employee advances'],
                        ['Loan', 'A real loan that may take a long time to repay.', 'Loans receivable from employees'],
                    ],
                },
                {
                    type: 'flow',
                    steps: ['Draft', 'Pending approval', 'Approved', 'Active', 'Settled'],
                },
                {
                    type: 'warn',
                    label: 'Approving ≠ paying',
                    text: 'When you approve a loan, no money moves and nothing is posted to the ledger — you have only authorised it. The actual payout is done with a separate "Pay" button.',
                },
                {
                    type: 'table',
                    headers: ['Loan field', 'Description'],
                    rows: [
                        ['Principal', 'The total loan amount.'],
                        ['Instalment', 'The amount withheld each payroll period.'],
                        ['Number of instalments', 'How many instalments it is repaid in.'],
                        ['Deduct from salary', 'If enabled, the system withholds one instalment automatically each payroll period. If off, you record repayments in cash.'],
                        ['First deduction from', 'Before this date, payroll withholds no instalment.'],
                        ['Remaining', 'The amount not yet repaid. This is calculated, not stored — from actual repayments. If you reverse a payroll period, that period’s instalment is removed and the remaining is corrected automatically.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'The final instalment',
                    text: 'If the loan remaining is less than the instalment, the system withholds only the remaining, not the full instalment.',
                },
                {
                    type: 'p',
                    text: 'Write-off: if repayment becomes impossible (usually when the employee has left), you can write off the remaining. The system moves the amount to the "employee costs" account and the loan closes with a "written off" status — not "settled" — so the report can say which loans were repaid and which were forgiven.',
                },
            ],
        },
        {
            id: 'reports',
            number: '11',
            title: 'Reports and testing',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Payroll register', 'Every payslip for a period with working days, earnings, tax, and amount paid. The main report for reviewing a payroll run.'],
                        ['Payroll summary', 'One row per payroll period. For seeing the trend over several months.'],
                        ['Withheld salary tax', 'Each employee’s tax with their tax ID and the tax table name. For filing with the Ministry of Finance.'],
                        ['Employee loans', 'All loans with principal, repaid, and remaining.'],
                    ],
                },
                {
                    type: 'note',
                    label: 'Reversed periods in reports',
                    text: 'Payroll periods that have been reversed or cancelled are not included in the payroll register report — because they do not represent money that was actually paid.',
                },
                {
                    type: 'tip',
                    label: 'Run a trial month first',
                    text: 'Before official use, take one payroll period with real data to the "calculated" stage and compare the numbers with a manual calculation. Until you post the period to the ledger, it has no effect on accounting.',
                },
            ],
        },
    ],
}
