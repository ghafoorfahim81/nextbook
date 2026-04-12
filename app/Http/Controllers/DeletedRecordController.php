<?php

namespace App\Http\Controllers;

use App\Services\DeletedRecordService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeletedRecordController extends Controller
{
    public function __construct(
        private readonly DeletedRecordService $deletedRecordService,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('deleted_records.view_any');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('System/DeletedRecords/Index', [
            ...$this->deletedRecordService->indexPayload($filters),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'module' => $filters['module'] ?? 'all',
                'per_page' => (int) ($filters['per_page'] ?? 25),
                'page' => (int) ($filters['page'] ?? 1),
            ],
        ]);
    }

    public function restore(Request $request, string $module, string $record): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('deleted_records.restore');

        $this->deletedRecordService->restore($module, $record);

        return back()->with('success', __('general.restored_successfully', ['resource' => 'record']));
    }

    public function destroy(Request $request, string $module, string $record): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('deleted_records.force_delete');

        $this->deletedRecordService->forceDelete($module, $record);

        return back()->with('success', __('general.permanently_deleted_successfully', ['resource' => 'record']));
    }
}
