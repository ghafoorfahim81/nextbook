<?php

use App\Models\ActivityLog;
use App\Models\Administration\Branch;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

it('attaches request and auth context when logging activity', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $this->actingAs($user);
    app()->instance('active_branch_id', $branch->id);
    app()->instance('request', Request::create(
        uri: '/activity-logs',
        method: 'GET',
        server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Pest Audit Test',
        ],
    ));

    $log = app(ActivityLogService::class)->log([
        'event_type' => 'export',
        'module' => 'report',
        'reference_type' => 'sale',
        'reference_id' => '01HRTESTREFERENCE0000000000',
        'description' => 'Sales report exported.',
        'metadata' => [
            'format' => 'csv',
        ],
    ]);

    expect($log)->toBeInstanceOf(ActivityLog::class)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->branch_id)->toBe((string) $branch->id)
        ->and($log->ip_address)->toBe('127.0.0.1')
        ->and($log->user_agent)->toBe('Pest Audit Test');
});

it('logs only changed fields for updates', function () {
    $service = app(ActivityLogService::class);

    $changes = $service->diff(
        before: [
            'status' => 'draft',
            'remark' => 'Original remark',
            'line_count' => 2,
        ],
        after: [
            'status' => 'posted',
            'remark' => 'Original remark',
            'line_count' => 3,
        ],
    );

    expect($changes['old_values'])->toBe([
        'status' => 'draft',
        'line_count' => 2,
    ])->and($changes['new_values'])->toBe([
        'status' => 'posted',
        'line_count' => 3,
    ]);
});
