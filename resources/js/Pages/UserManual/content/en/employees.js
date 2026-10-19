export default {
    id: 'employees',
    number: '8',
    title: 'Employees & contracts guide',
    subtitle: 'Org structure · the employee record · the employee ledger · contracts & documents',
    summary:
        'This guide is the foundation of the whole HR module: how you register an employee, how their ledger account works, and where their contract and documents live. Attendance, leave, and payroll all attach to this record.',
    chapters: [
        {
            id: 'hr-chain',
            number: '1',
            title: 'The HR chain',
            blocks: [
                {
                    type: 'p',
                    text: 'The HR module is not just a list of employee names; it is a full chain that connects daily attendance to the monthly salary, and the salary to accounting entries. The key principle: every piece of data is entered once and reused automatically many times.',
                },
                {
                    type: 'flow',
                    steps: ['Recruitment', 'Employee & contract', 'Attendance', 'Leave', 'Payroll', 'Accounting ledger'],
                },
                {
                    type: 'table',
                    headers: ['When you do this', 'The system automatically does this'],
                    rows: [
                        ['Hire a candidate', 'An employee record and a ledger account are created for them'],
                        ['Approve a leave request', 'Those days appear in attendance with a "leave" status'],
                        ['Record attendance', 'Present, absent, and overtime days are stored'],
                        ['Calculate payroll', 'A payslip with every component is built for each employee'],
                        ['Post payroll to the ledger', 'An entry with salary expense, tax, and a payable to each employee is written'],
                        ['Pay the salary', 'The payable to the employee is settled and cash leaves the account'],
                    ],
                },
                {
                    type: 'note',
                    label: 'Full PDF guide',
                    text: 'A complete printable version of this module exists at docs/hr-user-guide-fa.pdf. The in-app guides (Employees, Attendance, Leave, Payroll, Recruitment) present the same material split up and searchable.',
                },
            ],
        },
        {
            id: 'principles',
            number: '2',
            title: 'Three founding principles',
            blocks: [
                {
                    type: 'tip',
                    label: '1. The system ships defaults, but you are free',
                    text: 'When a branch is created, the system generates leave types, work shifts, salary components, and a salary tax table based on our reading of Afghan labour law and Ministry of Finance rules. But all of these are editable data, not fixed system rules.',
                },
                {
                    type: 'tip',
                    label: '2. No financial data is deleted',
                    text: 'Once a payroll period is posted to the ledger it cannot be deleted. If a mistake happens, you reverse it. A reversal keeps both the original and the reversing entry in the ledger, so the full history is explainable in an audit.',
                },
                {
                    type: 'tip',
                    label: '3. Approving and executing are separate permissions',
                    text: 'The person who prepares a salary or loan is not necessarily the person who approves it, and the approver is not necessarily the one who pays. The system deliberately keeps these steps apart to preserve internal control.',
                },
            ],
        },
        {
            id: 'org-structure',
            number: '3',
            title: 'Organisational structure',
            blocks: [
                {
                    type: 'p',
                    text: 'Before registering an employee, define two things. They are used in reports, the leave approval path, and the scope of a payroll run.',
                },
                {
                    type: 'table',
                    headers: ['Item', 'Meaning', 'Example'],
                    rows: [
                        ['Department', 'The organisational unit the employee works in', 'Finance, Sales, Technical, Admin'],
                        ['Designation', 'The employee’s job title or grade', 'Accountant, Sales manager, Engineer'],
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/employees/org-structure.png',
                    caption: 'The departments and designations page',
                    hint: 'screenshot of departments / designations',
                },
            ],
        },
        {
            id: 'employee-vs-ledger',
            number: '4',
            title: 'Every employee has two records',
            blocks: [
                {
                    type: 'p',
                    text: 'Each employee has two linked records:',
                },
                {
                    type: 'table',
                    headers: ['Record', 'What it holds'],
                    rows: [
                        ['Employee record', 'Personal, job, and bank details, documents, contract, attendance, leave'],
                        ['Employee ledger', 'All financial transactions: salary payable, payments made, loans and advances'],
                    ],
                },
                {
                    type: 'note',
                    label: 'Why a separate ledger?',
                    text: 'Double-entry accounting requires every company payable to be tied to a specific party. When you post the month’s payroll, the company owes each employee an amount, and that payable must sit in that employee’s account so that exactly the right amount is settled at payment time. The system creates this account automatically; its code looks like EMP-0001.',
                },
                {
                    type: 'warn',
                    label: 'Note',
                    text: 'The employee ledger does not appear in the customers and suppliers list. This is deliberate — employee salaries must not mix with trade transactions. Salary is paid from the "Pay payroll" menu, not the supplier payment form.',
                },
            ],
        },
        {
            id: 'personal-fields',
            number: '5',
            title: 'Employee record — personal details',
            blocks: [
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Employee code', 'The employee’s unique number in the branch. The system suggests it automatically (EMP-0001). The same code is used in their ledger account.'],
                        ['National ID', 'Unique per branch so one person is not registered twice.'],
                        ['TIN (tax ID)', 'The number the Ministry of Finance issues. Needed in the salary tax report and when filing tax with the ministry.'],
                        ['Father’s / grandfather’s name', 'Matters for identity in Afghanistan, where similar names are common.'],
                    ],
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/employees/employee-form.png',
                    caption: 'The employee form — personal, job, bank, and settings tabs',
                    hint: 'screenshot of the employee form',
                },
            ],
        },
        {
            id: 'job-fields',
            number: '6',
            title: 'Employee record — job details',
            blocks: [
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Employment type', 'Permanent, temporary, contract, consultant, daily wage, or trainee. This field is not just a label — it decides which expense account this employee’s salary posts to. Permanent, temporary, and consultant salaries post to three separate accounts so they can be told apart in financial reports.'],
                        ['Employment status', 'Active, probation, suspended, resigned, dismissed, or retired. Only employees with active or probation status are included in a payroll run.'],
                        ['Join date', 'First day of work. If the employee joins mid-period, their salary is prorated to the days worked.'],
                        ['Probation end', 'The system notifies you before this date so you can decide.'],
                        ['Reports to', 'Another employee this person reports to. Used for the org structure and the leave approval path.'],
                        ['Work shift', 'The employee’s default shift. It sets their working hours and which days are working days.'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Separation date',
                    text: 'Set the separation date only when the employment status is resignation, dismissal, or retirement.',
                },
            ],
        },
        {
            id: 'bank-settings-fields',
            number: '7',
            title: 'Employee record — bank and settings',
            blocks: [
                { type: 'h4', text: 'Bank details' },
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Payment method', 'Cash or through a bank.'],
                        ['Account number', 'The employee’s bank account number.'],
                        ['Account name', 'The name the bank account is registered under. Sometimes differs from the employee’s name (a joint account) and must be exact for a bank transfer.'],
                        ['IBAN', 'International Bank Account Number. Needed for transfers to or from abroad; usually left blank for domestic payments.'],
                    ],
                },
                { type: 'h4', text: 'Settings' },
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Allow self-service', 'When enabled, the employee can sign in with their own user account and record their own daily punches, and see their leave balance and payslip. Requirement: the employee must have a user account in the system.'],
                        ['Tax exempt', 'If enabled, no tax is withheld from this employee’s salary. A tax line with a zero amount still shows on the payslip so the exemption is visible.'],
                    ],
                },
            ],
        },
        {
            id: 'contracts-documents',
            number: '8',
            title: 'Contracts and documents',
            blocks: [
                {
                    type: 'p',
                    text: 'You can record several contracts and documents for each employee. The difference:',
                },
                {
                    type: 'table',
                    headers: ['Item', 'Use'],
                    rows: [
                        ['Contract', 'The formal employment document with start and end dates, agreed salary, notice period, and working hours. The system notifies before the contract ends.'],
                        ['Document', 'National ID, passport, visa, work permit, diploma, certificate, medical record, etc. Each document can have an expiry date, and the system notifies before it expires.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Why this distinction matters',
                    text: 'A foreign employee’s work permit expiring is operationally just as disruptive as a contract ending. The system tracks and notifies on both so neither is forgotten.',
                },
            ],
        },
        {
            id: 'delete',
            number: '9',
            title: 'Deleting and restoring an employee',
            blocks: [
                {
                    type: 'p',
                    text: 'If you delete an employee, their record and their ledger account move together to "deleted records" and can be restored. But if the employee has an accounting transaction (received salary or has a loan), the system does not allow a full permanent delete — because deleting them would orphan accounting entries.',
                },
                {
                    type: 'tip',
                    label: 'Phone and email',
                    text: 'Contact details stay on the employee record and are not copied to the ledger; two people can share one number.',
                },
            ],
        },
        {
            id: 'full-flow',
            number: '10',
            title: 'Full flow — from hire to a complete record',
            blocks: [
                { type: 'h4', text: 'Setup (once)' },
                {
                    type: 'ol',
                    items: [
                        'Define departments and designations (HR → Departments / Designations).',
                        'Review and set up work shifts (Attendance → Shifts).',
                        'Record the year’s public holidays (Attendance → Public holidays).',
                        'Reconcile leave types and the tax table with company policy and current regulations.',
                        'Define salary components (allowances and deductions).',
                    ],
                },
                { type: 'h4', text: 'Completing a new employee’s record' },
                {
                    type: 'ol',
                    items: [
                        'Create the employee from the recruitment process (or register directly).',
                        'Enter bank details, national ID, and TIN.',
                        'Set their work shift.',
                        'Record their employment contract and attach documents (ID, diploma).',
                        'Build a salary structure for them with an effective date equal to the join date.',
                        'Record their annual leave allocation.',
                    ],
                },
            ],
        },
        {
            id: 'reports',
            number: '11',
            title: 'Related reports',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Headcount', 'Number of employees per department at period end, with hires and leavers. This report counts at the moment of period end, not the period average.'],
                        ['Contract end', 'Contracts ending in this period, so a renewal or notice period is not forgotten.'],
                    ],
                },
            ],
        },
    ],
}
