<?php

return [
    'employee' => 'کارمند',
    'employees' => 'کارمندان',
    'contract' => 'قرارداد',
    'contracts' => 'قراردادها',
    'document' => 'سند',
    'documents' => 'اسناد',
    'direct_reports' => 'زیردستان',
    'manager' => 'آمر',
    'department' => 'بخش',
    'designation' => 'وظیفه',

    'validation' => [
        'after_joining_date' => 'این تاریخ نمی‌تواند پیش از تاریخ شمولیت باشد.',
        'dob_before_joining' => 'تاریخ تولد باید پیش از تاریخ شمولیت باشد.',
        'separation_date_required' => 'برای کارمندی که وظیفه را ترک کرده، تاریخ انفکاک ضروری است.',
        'separation_date_not_allowed' => 'تا زمانی که کارمند مصروف وظیفه است، تاریخ انفکاک ثبت شده نمی‌تواند.',
        'end_date_required' => 'این نوع قرارداد به تاریخ ختم ضرورت دارد.',
        'end_date_after_start' => 'تاریخ ختم باید پس از تاریخ آغاز باشد.',
        'overlapping_contract' => 'این کارمند در این تاریخ‌ها قرارداد فعال دارد.',
        'expiry_after_issue' => 'تاریخ انقضا باید پس از تاریخ صدور باشد.',
        'manager_cannot_be_self' => 'کارمند نمی‌تواند آمر خودش باشد.',
        'manager_cycle' => 'این سلسله‌مراتب حلقه ایجاد می‌کند.',
    ],

    'notifications' => [
        'contract_expiring_title' => 'ختم قرارداد',
        'contract_expiring_body' => 'قرارداد (:number) «:employee» به تاریخ :date ختم می‌شود.',
        'document_expiring_title' => 'انقضای سند',
        'document_expiring_body' => ':type «:employee» به تاریخ :date منقضی می‌شود.',
        'probation_ending_title' => 'ختم دوره آزمایشی',
        'probation_ending_body' => 'دوره آزمایشی «:employee» به تاریخ :date ختم می‌شود.',
    ],

    'errors' => [
        'ledger_has_history' => 'این کارمند سابقه حسابی دارد و به‌صورت دایمی حذف شده نمی‌تواند.',
    ],
];
