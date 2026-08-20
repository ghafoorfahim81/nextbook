<?php

return [
    'employee' => 'Employee',
    'employees' => 'Employees',
    'contract' => 'Contract',
    'contracts' => 'Contracts',
    'document' => 'Document',
    'documents' => 'Documents',
    'direct_reports' => 'Direct reports',
    'manager' => 'Manager',
    'department' => 'Department',
    'designation' => 'Designation',

    'validation' => [
        'after_joining_date' => 'This date cannot be earlier than the joining date.',
        'dob_before_joining' => 'Date of birth must be before the joining date.',
        'separation_date_required' => 'A separation date is required once the employee has left.',
        'separation_date_not_allowed' => 'A separation date cannot be set while the employee is still employed.',
        'end_date_required' => 'This contract type requires an end date.',
        'end_date_after_start' => 'The end date must be after the start date.',
        'overlapping_contract' => 'This employee already has a contract covering these dates.',
        'expiry_after_issue' => 'The expiry date must be after the issue date.',
        'manager_cannot_be_self' => 'An employee cannot report to themselves.',
        'manager_cycle' => 'This reporting line would create a loop.',
    ],

    'notifications' => [
        'contract_expiring_title' => 'Contract expiring',
        'contract_expiring_body' => "':employee' has a contract (:number) expiring on :date.",
        'document_expiring_title' => 'Document expiring',
        'document_expiring_body' => "':employee' has a :type expiring on :date.",
        'probation_ending_title' => 'Probation ending',
        'probation_ending_body' => "':employee' finishes probation on :date.",
    ],

    'errors' => [
        'ledger_has_history' => 'This employee has accounting history and cannot be permanently deleted.',
    ],
];
