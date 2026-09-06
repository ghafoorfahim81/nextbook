export default {
    id: 'recruitment',
    number: '12',
    title: 'Recruitment guide',
    subtitle: 'Vacancy · candidate stages · interviews & panel opinion · hiring a candidate',
    summary:
        'This module manages the recruitment process from posting a vacancy to hiring the final candidate: receiving applications, shortlisting, interviewing, and finally converting a candidate to an employee.',
    chapters: [
        {
            id: 'principle',
            number: '1',
            title: 'Founding principle: a candidate is not an employee',
            blocks: [
                {
                    type: 'note',
                    label: 'A candidate has no ledger account',
                    text: 'All candidates are stored in a separate table and get no ledger account. Why: most candidates are never hired. If they were in the employee table, all headcount and payroll reports would be wrong, and if they had ledger accounts, the general ledger would fill with people the company never paid. This boundary is crossed only once: the moment of hiring.',
                },
            ],
        },
        {
            id: 'vacancy',
            number: '2',
            title: 'The vacancy',
            blocks: [
                {
                    type: 'table',
                    headers: ['Field', 'Description'],
                    rows: [
                        ['Positions (vacancies)', 'How many people are hired for this title. The system also shows the number remaining.'],
                        ['Recruitment owner', 'The employee responsible for this process.'],
                        ['Salary from / to', 'The proposed salary range. Useful when negotiating with a candidate.'],
                        ['Close date', 'The last day applications are accepted. The system automatically "closes" vacancies whose close date has passed.'],
                    ],
                },
                {
                    type: 'flow',
                    steps: ['Draft', 'Published', 'Closed', 'Filled'],
                },
                {
                    type: 'note',
                    label: 'The difference between "closed" and "filled"',
                    text: '"Closed" means no new applications are accepted, but candidates already in the interview process still need to reach a conclusion. "Filled" means all positions are taken. When the close date passes, the system "closes" the vacancy, not "cancels" it — ending the posting does not mean abandoning existing candidates.',
                },
                {
                    type: 'figure',
                    src: '/images/user-manual/recruitment/vacancy.png',
                    caption: 'The vacancy form',
                    hint: 'screenshot of the vacancy form',
                },
            ],
        },
        {
            id: 'candidate-stages',
            number: '3',
            title: 'Candidate stages',
            blocks: [
                {
                    type: 'flow',
                    steps: ['Applied', 'Shortlisted', 'Interviewing', 'Offered', 'Hired'],
                },
                {
                    type: 'table',
                    headers: ['Status', 'Meaning'],
                    rows: [
                        ['Applied', 'The application is received but not yet reviewed.'],
                        ['Shortlisted', 'Found eligible. Interviews can be scheduled from this stage.'],
                        ['Interviewing', 'At least one interview is scheduled.'],
                        ['Offered', 'A job offer with a specific salary has been made.'],
                        ['Hired', 'The candidate has been converted to an employee.'],
                        ['Rejected', 'The company did not accept them.'],
                        ['Declined', 'The candidate themselves declined.'],
                    ],
                },
                {
                    type: 'tip',
                    label: 'Why are "rejected" and "declined" separate?',
                    text: 'A candidate who found another job and declined is someone you might contact again in future. If they were in the "rejected" list, that list would be useless.',
                },
            ],
        },
        {
            id: 'interviews',
            number: '4',
            title: 'Interviews and panel opinion',
            blocks: [
                {
                    type: 'p',
                    text: 'You can schedule several interview rounds for each candidate. Each interview can have several panel members, and each member records their opinion separately.',
                },
                {
                    type: 'note',
                    label: 'Why is each member’s opinion separate?',
                    text: 'Because a disagreement between two interviewers is usually the most useful information in the whole file. If everyone writes in one shared box, that disagreement is lost.',
                },
                { type: 'h4', text: 'Final panel opinion logic' },
                {
                    type: 'list',
                    items: [
                        'The system does not average the final opinion.',
                        'If even one person is "strongly against hiring", the panel’s final opinion is "strongly against" — even if everyone else agrees.',
                        'Otherwise, if the majority is positive, the final opinion is "recommend hiring".',
                        'Numeric scores are averaged, but only to sort the candidate list, not to decide.',
                    ],
                },
            ],
        },
        {
            id: 'hire',
            number: '5',
            title: 'Hiring a candidate',
            blocks: [
                {
                    type: 'p',
                    text: 'When you press "Hire":',
                },
                {
                    type: 'ol',
                    items: [
                        'An employee record is created with the candidate’s details.',
                        'The employee ledger account is created automatically.',
                        'Their employment status is set to "probation".',
                        'The candidate application moves to "hired" and is linked to the employee record.',
                        'If the last vacancy is filled, the vacancy becomes "filled".',
                    ],
                },
                {
                    type: 'warn',
                    label: 'Hiring beyond the number of positions',
                    text: 'If the vacancies are used up, the system does not allow hiring. This is deliberate — hiring beyond the approved number is a budget decision and must be visible. The fix is to increase the number of positions on the posting so the change is recorded.',
                },
                {
                    type: 'note',
                    label: 'About the candidate’s name',
                    text: 'At hiring, the system splits the candidate’s full name into "first name" and "last name". But in Afghanistan many people have no last name, so this split is just an initial guess. After hiring, review the name in the employee form and fix it if needed.',
                },
            ],
        },
    ],
}
