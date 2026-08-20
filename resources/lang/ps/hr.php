<?php

return [
    'employee' => 'کارکوونکی',
    'employees' => 'کارکوونکي',
    'contract' => 'قرارداد',
    'contracts' => 'قراردادونه',
    'document' => 'سند',
    'documents' => 'اسناد',
    'direct_reports' => 'لاندې کارکوونکي',
    'manager' => 'آمر',
    'department' => 'څانګه',
    'designation' => 'دنده',

    'validation' => [
        'after_joining_date' => 'دا نېټه د شاملېدو له نېټې مخکې نشي کېدای.',
        'dob_before_joining' => 'د زېږېدنې نېټه باید د شاملېدو له نېټې مخکې وي.',
        'separation_date_required' => 'د هغه کارکوونکي لپاره چې دنده یې پرېښې، د بېلتون نېټه اړینه ده.',
        'separation_date_not_allowed' => 'تر هغه چې کارکوونکی په دنده بوخت وي، د بېلتون نېټه نشي ثبتېدای.',
        'end_date_required' => 'دا ډول قرارداد د پای نېټې ته اړتیا لري.',
        'end_date_after_start' => 'د پای نېټه باید د پیل له نېټې وروسته وي.',
        'overlapping_contract' => 'دې کارکوونکي په دې نېټو کې فعال قرارداد لري.',
        'expiry_after_issue' => 'د پای نېټه باید د صادرېدو له نېټې وروسته وي.',
        'manager_cannot_be_self' => 'کارکوونکی د ځان آمر نشي کېدای.',
        'manager_cycle' => 'دا لړۍ حلقه جوړوي.',
    ],

    'notifications' => [
        'contract_expiring_title' => 'د قرارداد پای',
        'contract_expiring_body' => 'د «:employee» قرارداد (:number) په :date پای ته رسېږي.',
        'document_expiring_title' => 'د سند پای',
        'document_expiring_body' => 'د «:employee» :type په :date پای ته رسېږي.',
        'probation_ending_title' => 'د ازمایښتي دورې پای',
        'probation_ending_body' => 'د «:employee» ازمایښتي دوره په :date پای ته رسېږي.',
    ],

    'errors' => [
        'ledger_has_history' => 'دا کارکوونکی حسابي سابقه لري او د تل لپاره نشي حذفېدای.',
    ],
];
