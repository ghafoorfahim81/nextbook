export default {
    id: 'leave',
    number: '10',
    title: 'Leave guide',
    subtitle: 'Leave types · allocation · balance formula · accrual method · request stages · rules',
    summary:
        'This module manages leave types, each employee’s annual entitlement, leave requests, and their approval. An approved leave appears automatically in attendance and is honoured by payroll.',
    chapters: [
        {
            id: 'three-concepts',
            number: '1',
            title: 'Three core concepts',
            blocks: [
                {
                    type: 'table',
                    headers: ['Concept', 'Meaning'],
                    rows: [
                        ['Leave type', 'The definition of a kind of leave and its rules — for example "Annual leave, 20 days, paid". This is a definition, not one person’s entitlement.'],
                        ['Leave allocation', 'A specific employee’s entitlement to a leave type in a specific period. For example "Ahmad, annual leave, year 1405, 20 days".'],
                        ['Leave request', 'An employee’s request to use leave on specific dates.'],
                    ],
                },
            ],
        },
        {
            id: 'default-types',
            number: '2',
            title: 'Default leave types',
            blocks: [
                {
                    type: 'p',
                    text: 'Based on our reading of Afghan labour law, the system creates these types by default:',
                },
                {
                    type: 'table',
                    headers: ['Leave type', 'Days per year', 'Paid', 'Special conditions'],
                    rows: [
                        ['Annual leave', '20', 'Yes', 'Can be carried forward (up to a set cap)'],
                        ['Sick leave', '20', 'Yes', 'A medical record is usually required'],
                        ['Emergency leave', '10', 'Yes', 'For urgent family matters'],
                        ['Maternity leave', '90', 'Yes', 'Female employees only'],
                        ['Hajj leave', '45', 'Yes', 'At least 24 months of service required'],
                        ['Unpaid leave', '—', 'No', 'Deducted from salary'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Important',
                    text: 'These figures are set from our reading of Afghan labour law but are not legal advice. Before official use, reconcile them with the current rules of the Ministry of Labour and Social Affairs. All of these figures are editable.',
                },
            ],
        },
        {
            id: 'balance-formula',
            number: '3',
            title: 'The leave balance formula',
            blocks: [
                {
                    type: 'p',
                    text: 'The system does not store the leave balance; it calculates it each time. The formula:',
                },
                {
                    type: 'formula',
                    text: 'Available = entitled + carried forward + adjustment − approved days − encashed − expired',
                },
                {
                    type: 'table',
                    headers: ['Formula part', 'Description'],
                    rows: [
                        ['Entitled', 'The number of days the employee is entitled to this period. Usually the leave type’s "days per year".'],
                        ['Carried forward', 'Unused days from last year moved into this year. The carry cap is controlled by "max carry forward" on the leave type; the excess is recorded under "expired".'],
                        ['Adjustment', 'A manual correction by HR. Can be positive (a few bonus leave days) or negative (fixing a mistake).'],
                        ['Approved days', 'The total of leave days that have been approved. Pending requests are not included.'],
                        ['Encashed', 'Unused leave days the employee took the cash equivalent of instead of using. These are deducted from the balance because the employee cannot both take the money and take the leave. Only possible for "encashable" leave types.'],
                        ['Expired', 'Days that were lost — either not carried because of the carry cap, or carried but not used within the allowed window.'],
                        ['Pending', 'This is not deducted from the balance. Requests not yet approved are shown separately so the employee knows how much they have in progress.'],
                    ],
                },
            ],
        },
        {
            id: 'accrual',
            number: '4',
            title: 'Leave accrual method',
            blocks: [
                {
                    type: 'table',
                    headers: ['Method', 'Description'],
                    rows: [
                        ['Annual grant', 'The full entitlement is given at the start of the year. The simplest method.'],
                        ['Monthly accrual', 'Entitlement builds month by month. If it is 12 days a year, 1 day is added each month. An employee who has worked 3 months has 3 days.'],
                        ['Unlimited', 'No cap — for types such as unpaid leave.'],
                    ],
                },
                { type: 'h4', text: 'Pro-rata on join' },
                {
                    type: 'p',
                    text: 'If enabled, an employee who joins mid-year does not get the full entitlement but a share proportional to the remaining months:',
                },
                {
                    type: 'formula',
                    text: 'Entitlement = days per year × (remaining months ÷ 12)',
                },
                {
                    type: 'p',
                    text: 'The join month counts if the employee joined before mid-month. An employee who joins on the 10th of a month gets that month counted; joining on the 20th, the count starts from the next month.',
                },
            ],
        },
        {
            id: 'request-stages',
            number: '5',
            title: 'Leave request stages',
            blocks: [
                {
                    type: 'flow',
                    steps: ['Draft', 'Pending approval', 'Approved'],
                },
                {
                    type: 'table',
                    headers: ['Stage', 'Meaning and what is possible'],
                    rows: [
                        ['Draft', 'Not submitted yet. Editable and deletable.'],
                        ['Pending approval', 'Submitted and waiting on the manager. Days are not yet deducted from the balance.'],
                        ['Approved', 'The manager approved. Days are deducted from the balance and appear in attendance.'],
                        ['Rejected', 'The manager rejected. The dates are free again.'],
                        ['Cancelled', 'Cancelled before the leave started. Future days are removed from attendance.'],
                        ['Withdrawn', 'Withdrawn by HR after the leave started.'],
                    ],
                },
                {
                    type: 'warn',
                    label: 'Do not type the day count by hand',
                    text: 'The day count is calculated from the leave type and the calendar — Fridays and public holidays may not count. Do not enter the number manually.',
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/leave/request.png',
                    caption: 'The leave request form with the balance shown',
                    hint: 'screenshot of the leave request form',
                },
            ],
        },
        {
            id: 'rules',
            number: '6',
            title: 'Rules the system enforces',
            blocks: [
                {
                    type: 'p',
                    text: 'When recording or approving a request, the system checks:',
                },
                {
                    type: 'list',
                    items: [
                        'Date overlap: if the employee has another request on the same dates, it is not allowed.',
                        'Notice period: if the leave type has a notice period (say 7 days ahead), it is enforced.',
                        'Maximum consecutive days: if a cap is set.',
                        'Minimum service: Hajj leave, for example, needs 24 months of service.',
                        'Gender: maternity leave is female employees only.',
                        'Sufficient balance: if the balance is short, approval is refused — unless the leave type is "deduct from salary", in which case a negative balance is allowed and is deducted as unpaid leave in the payroll calculation.',
                        'Approving is a separate permission from creating the request.',
                    ],
                },
            ],
        },
        {
            id: 'glossary-reports',
            number: '7',
            title: 'Glossary and reports',
            blocks: [
                {
                    type: 'table',
                    headers: ['Term', 'Meaning'],
                    rows: [
                        ['Entitled', 'The number of leave days the employee is entitled to in a period, before carry forward and used days.'],
                        ['Available', 'The employee’s real leave balance right now — the result of the chapter 3 formula.'],
                        ['Encashed', 'Unused days the employee took the cash equivalent of.'],
                        ['Pending', 'Requested but not yet approved days; not deducted from the balance.'],
                    ],
                },
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Leave balance', 'Entitled versus used for each employee and each leave type.'],
                        ['Leave register', 'All leave requests with dates and approver.'],
                    ],
                },
            ],
        },
    ],
}
