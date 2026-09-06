export default {
    id: 'hr',
    number: '8',
    title: 'Human resources guide',
    subtitle: 'Employees · contracts · attendance · leave · payroll · recruitment',
    summary:
        'HR is a chain: recruitment, employee and contract, attendance and leave, then payroll calculation and payment. A full, detailed guide to this module is also available as a separate file.',
    chapters: [
        {
            id: 'chain',
            number: '1',
            title: 'The HR chain',
            blocks: [
                {
                    type: 'flow',
                    steps: ['Recruitment', 'Employee & contract', 'Attendance', 'Leave', 'Payroll', 'Accounting ledger'],
                },
                {
                    type: 'p',
                    text: 'Every piece of data is entered once and reused many times. Daily attendance is the input to payroll; approved leave appears automatically in attendance; posted payroll lands as entries in the accounting ledger.',
                },
                {
                    type: 'note',
                    label: 'Full HR guide',
                    text: 'A detailed, step-by-step guide to the HR module (employees, attendance, leave, payroll, loans, recruitment) exists as a separate document at docs/hr-user-guide-fa.pdf. This chapter is a summary of it.',
                },
            ],
        },
        {
            id: 'employees',
            number: '2',
            title: 'Employees and contracts',
            blocks: [
                {
                    type: 'table',
                    headers: ['Page', 'Purpose'],
                    rows: [
                        ['Employees', 'Personal and job profile, employment type, and the linked payroll ledger.'],
                        ['Contracts', 'Start and end dates, agreed salary, terms, and renewal.'],
                        ['Documents', 'Copies of the ID, contract, diploma, and other files with expiry dates.'],
                        ['Departments', 'The org tree for attendance and payroll scope.'],
                        ['Designations', 'Job title; a salary structure can be attached to a designation.'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'Employment type (permanent, temporary, contract, consultant) decides which expense account payroll posts to.',
                        'Registering an employee creates a ledger account (code EMP-0001) for their payroll and loans.',
                        'Set the separation date only when the status is resignation, dismissal, or retirement.',
                        'Deleting an employee hides their ledger; if they have a posted transaction, permanent deletion is blocked.',
                    ],
                },
            ],
        },
        {
            id: 'attendance',
            number: '3',
            title: 'Attendance',
            blocks: [
                {
                    type: 'p',
                    text: 'Attendance can be recorded on the daily roster, imported from a fingerprint device, or entered by the employee from “My attendance”. All three create one daily record.',
                },
                {
                    type: 'list',
                    items: [
                        'Before recording attendance, a work shift must be defined (start/end time, working days, grace minutes).',
                        'Re-importing the same device file is safe; a duplicate punch is rejected.',
                        'A day marked “needs review” usually has an unpaired punch; fix it on the daily roster.',
                        'When a period’s payroll is posted, that period’s attendance days lock. Reverse payroll first.',
                    ],
                },
            ],
        },
        {
            id: 'leave',
            number: '4',
            title: 'Leave',
            blocks: [
                {
                    type: 'p',
                    text: 'First create leave types (annual, sick, unpaid), then allocate a balance to each employee. The employee requests leave; the request moves from draft to “pending” and then “approved”. Approval writes the days automatically into attendance.',
                },
                {
                    type: 'formula',
                    text: 'Available = entitled + carried forward + adjustment − approved days − encashed − expired',
                },
                {
                    type: 'list',
                    items: [
                        'The day count is calculated from the leave type (Fridays and public holidays may not count). Do not type the number by hand.',
                        '“Pending” days are shown but not deducted from the balance until approval.',
                        'If the balance is short, approval is refused — except for a “deduct from salary” leave type.',
                        'Approving is a separate permission from creating the request.',
                    ],
                },
            ],
        },
        {
            id: 'payroll',
            number: '5',
            title: 'Payroll',
            blocks: [
                {
                    type: 'flow',
                    steps: ['Structure & components', 'Draft & calculate', 'Post payroll', 'Pay payroll'],
                },
                {
                    type: 'ol',
                    items: [
                        'Define salary components (basic, allowance, deduction).',
                        'Build a salary structure and attach it to an employee, designation, or department.',
                        'If you withhold salary tax, set a tax table.',
                        'Create a payroll period, calculate it (recalculating a draft is safe), review payslips, then post.',
                        'Pay the net amount with “Pay payroll”.',
                        'Record loans and advances; payroll can auto-deduct the instalment.',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Posted payroll locks attendance',
                    text: 'A posted month-day can only be fixed after reversing that payroll period. Posting payroll can be reversed but not edited.',
                },
            ],
        },
        {
            id: 'recruitment',
            number: '6',
            title: 'Recruitment',
            blocks: [
                {
                    type: 'p',
                    text: 'Open a job opening, collect candidate applications, and record interviews. After the final choice, create the employee directly from that candidate so their attendance and payroll begin.',
                },
            ],
        },
    ],
}
