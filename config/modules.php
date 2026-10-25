<?php

/**
 * The modules a company can switch on or off (phase 6).
 *
 * Sale, purchase, inventory, accounts and ledgers are the core and are not
 * listed: they are always on. A module here is on or off per company, through
 * the company setting `modules.<key>` (config/preferences.php). Its business
 * type gives the default (business_profiles.profiles.<type>.settings.modules),
 * the company's settings page can change it.
 *
 * When a module is off it is hidden and blocked, never deleted:
 *   - EnsureModuleEnabled answers its URLs with "module not enabled";
 *   - the sidebar and the global search leave out anything under its paths.
 * Its records stay as they are and come back when it is switched on again.
 *
 * `paths` are first URL segments ("/employees" covers /employees/123/edit).
 *
 * `available` => false marks a module that is planned but not built yet. It is
 * registered so its per-type defaults can be set now, and it stays out of the
 * settings page until it exists. Building one means: its routes, its paths
 * here, then `available` => true.
 */
return [

    'hr' => [
        'available' => true,
        'paths' => [
            'employees', 'employee-contracts', 'employee-documents', 'employee-loans',
            'departments', 'designations',
            'attendances', 'attendance-devices', 'attendance-device-mappings', 'shifts', 'holidays',
            'leave-requests', 'leave-types', 'leave-allocations',
            'payrolls', 'salary-components', 'salary-structures', 'salary-payments', 'tax-bracket-sets',
            'job-openings', 'job-applications', 'interviews',
            // Employee self-service.
            'my',
        ],
    ],

    'owners' => [
        'available' => true,
        'paths' => ['owners', 'drawings'],
    ],

    'expenses' => [
        'available' => true,
        'paths' => ['expenses', 'expense-categories'],
    ],

    'landed_costs' => [
        'available' => true,
        'paths' => ['landed-costs', 'landed-cost-categories'],
    ],

    'discount_rules' => [
        'available' => true,
        'paths' => ['discount-rules'],
    ],

    // Planned, not built yet.
    'scm' => ['available' => false, 'paths' => []],
    'pos' => ['available' => false, 'paths' => []],
    'crm' => ['available' => false, 'paths' => []],
    'procurement' => ['available' => false, 'paths' => []],
    'projects' => ['available' => false, 'paths' => []],
    'fixed_assets' => ['available' => false, 'paths' => []],
    'bi' => ['available' => false, 'paths' => []],
];
