export default {
    id: 'attendance',
    number: '9',
    title: 'Attendance guide',
    subtitle: 'Work shift · three ways to record · punch pairing · statuses & priority · locking',
    summary:
        'Attendance records whether each employee was present each day, when they arrived and left, and whether they worked overtime. The result feeds directly into payroll calculation.',
    chapters: [
        {
            id: 'purpose',
            number: '1',
            title: 'Purpose of this module',
            blocks: [
                {
                    type: 'p',
                    text: 'This module creates one record per employee per day: present or absent, arrival and departure time, hours worked, and overtime hours. Payroll calculation uses these numbers directly, so attendance accuracy = payroll accuracy.',
                },
                {
                    type: 'table',
                    headers: ['Page', 'Purpose'],
                    rows: [
                        ['Daily roster', 'Pick a date and a department and confirm or correct each employee’s status.'],
                        ['Register', 'History of daily records.'],
                        ['Unmapped punches', 'A device ID not yet linked to any employee.'],
                        ['Devices', 'Attendance devices and their connection.'],
                        ['Shifts', 'Working hours for the roster and calculation.'],
                        ['Public holidays', 'Official holidays that a leave type can override.'],
                        ['My attendance', 'Punch in and out for the signed-in employee.'],
                    ],
                },
            ],
        },
        {
            id: 'shift',
            number: '2',
            title: 'The work shift — the basis of attendance',
            blocks: [
                {
                    type: 'p',
                    text: 'Before recording attendance, a work shift must be defined. The shift decides what a "normal working day" means for a group of employees.',
                },
                {
                    type: 'table',
                    headers: ['Shift field', 'Description'],
                    rows: [
                        ['Start / end time', 'The official start and end of work. The system default is 8:00 AM to 4:00 PM.'],
                        ['Working days', 'Which days of the week are working days. The Afghanistan default is Saturday to Thursday with Friday off. An organisations shift (Saturday to Wednesday) also exists.'],
                        ['Grace minutes', 'How many minutes of lateness are ignored. If grace is 15 minutes and the employee arrives at 8:10, it is not late; at 8:20, 5 minutes late is recorded.'],
                        ['Full-day hours', 'How many hours count as a full day (usually 8). Working more than this is overtime.'],
                        ['Half-day hours', 'If the employee works less than a full day but more than this, attendance is recorded as "half day".'],
                        ['Crosses midnight', 'For night shifts. If the shift is 10 PM to 6 AM, enable this so the system knows the checkout is on the next day and does not count it as absent.'],
                    ],
                },
                {
                    type: 'note',
                    label: 'Ramadan shift',
                    text: 'The system creates a 6-hour shift for Ramadan by default. Activate it during Ramadan so overtime is calculated against 6 hours, not 8.',
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/attendance/shift.png',
                    caption: 'The work shift form',
                    hint: 'screenshot of the shift form',
                },
            ],
        },
        {
            id: 'three-methods',
            number: '3',
            title: 'Three ways to record attendance',
            blocks: [
                {
                    type: 'p',
                    text: 'The system has three methods and you can use all three at once:',
                },
                {
                    type: 'table',
                    headers: ['Method', 'How it works', 'Suited to'],
                    rows: [
                        ['1. Daily roster', 'A table showing all employees for one day. The attendance officer picks each person’s status. "All present" and "Copy from yesterday" buttons speed it up.', 'Small and medium offices with no attendance device'],
                        ['2. Device import', 'You import the fingerprint device’s export file. The system pairs check-in and check-out and builds attendance.', 'Companies with an attendance device'],
                        ['3. Self-service', 'The employee records their own check-in and check-out with their user account.', 'Office staff with computer or mobile access'],
                    ],
                },
                {
                    type: 'list',
                    items: [
                        'Re-importing the same device file is safe; a duplicate punch is rejected.',
                        'Self-service requires the employee’s "system user" field to be filled and the "allow self-service" option to be on.',
                    ],
                },
            ],
        },
        {
            id: 'pairing',
            number: '4',
            title: 'Check-in / check-out pairing logic',
            blocks: [
                {
                    type: 'p',
                    text: 'When device data is imported, the system just has a list of times and must work out which is a check-in and which a check-out. The logic:',
                },
                {
                    type: 'ol',
                    items: [
                        'If the device itself marks the direction (in/out), the system trusts it.',
                        'If the direction is not marked (most cheap devices), the first punch within the shift window is treated as check-in and the last as check-out.',
                        'Middle punches are treated as break time (lunch) and deducted from hours worked.',
                        'If only one punch exists (the employee arrived but did not punch out), the system records it as a check-in and marks the day "needs review".',
                    ],
                },
                {
                    type: 'warn',
                    label: 'What does "needs review" mean?',
                    text: 'It means the system is not sure and wants a human to look. It usually happens when the employee forgot to punch out. Review and fix these days before running payroll, because their hours are incomplete.',
                },
            ],
        },
        {
            id: 'statuses',
            number: '5',
            title: 'Attendance statuses and priority order',
            blocks: [
                {
                    type: 'p',
                    text: 'A day can meet several conditions at once — for example both a public holiday and a personal leave. The system decides in this order (top to bottom, first wins):',
                },
                {
                    type: 'table',
                    headers: ['Order', 'Status', 'Meaning'],
                    rows: [
                        ['1', 'Leave', 'An approved leave exists on this day. This status wins over everything.'],
                        ['2', 'Public holiday', 'The day is in the public holidays list (Eid, national days).'],
                        ['3', 'Weekend', 'The day is not a working day in the employee’s shift (usually Friday).'],
                        ['4', 'Present / late', 'The employee showed up. If they arrived after the grace period, "late" is recorded.'],
                        ['5', 'Half day', 'Hours worked were below a full day but above the half-day threshold.'],
                        ['6', 'Absent', 'No punch exists and it was a working day.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Why does leave win over a public holiday?',
                    text: 'Because approved leave is an administrative decision that was recorded and deducted from the employee’s balance. If the system recorded that day as a public holiday, it would not be clear why the balance went down.',
                },
            ],
        },
        {
            id: 'locking',
            number: '6',
            title: 'Attendance locking',
            blocks: [
                {
                    type: 'warn',
                    label: 'Days of a posted payroll period are locked',
                    text: 'When a payroll period is posted to the ledger, that period’s attendance days lock and can no longer be changed. The reason is simple: payroll was calculated from those numbers, and if they change, the payslip no longer matches reality. If attendance really must be fixed, reverse the payroll first — that unlocks attendance automatically.',
                },
            ],
        },
        {
            id: 'glossary',
            number: '7',
            title: 'Glossary',
            blocks: [
                {
                    type: 'table',
                    headers: ['Term', 'Meaning'],
                    rows: [
                        ['Grace minutes', 'How many minutes of lateness are ignored without recording "late".'],
                        ['Crosses midnight', 'For night shifts; tells the system the checkout is on the next calendar day and must not count as absent.'],
                        ['Needs review', 'The system was unsure pairing check-in and check-out — usually because the employee did not punch out.'],
                        ['Unmapped punch', 'A device ID not yet linked to any employee.'],
                    ],
                },
            ],
        },
        {
            id: 'reports',
            number: '8',
            title: 'Attendance reports',
            blocks: [
                {
                    type: 'table',
                    headers: ['Report', 'What it shows'],
                    rows: [
                        ['Attendance summary', 'Number of present, late, absent, and leave days per employee in a period.'],
                        ['Attendance register', 'Day-by-day records with check-in and check-out times. For investigating one case.'],
                    ],
                },
            ],
        },
    ],
}
