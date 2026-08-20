<?php

namespace App\Http\Controllers\Hr;

use App\Enums\EmployeeDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeeDocumentStoreRequest;
use App\Http\Requests\Hr\EmployeeDocumentUpdateRequest;
use App\Http\Resources\Hr\EmployeeDocumentResource;
use App\Models\Hr\EmployeeDocument;
use App\Services\ActivityLogService;
use App\Services\AttachmentService;
use App\Services\DateConversionService;
use App\Services\DeletedRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeDocumentController extends Controller
{
    private DateConversionService $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(EmployeeDocument::class, 'employee_document');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortableFields = [
            'document_type' => 'document_type',
            'document_number' => 'document_number',
            'issue_date' => 'issue_date',
            'expiry_date' => 'expiry_date',
        ];
        $sortColumn = $sortableFields[$request->input('sortField', 'expiry_date')] ?? 'expiry_date';

        $documents = EmployeeDocument::query()
            ->with(['employee:id,full_name,code', 'verifiedBy:id,name', 'createdBy:id,name'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/EmployeeDocuments/Index', [
            'documents' => EmployeeDocumentResource::collection($documents),
            'filterOptions' => [
                'documentTypes' => array_map(
                    fn (EmployeeDocumentType $case) => ['id' => $case->value, 'name' => $case->getLabel()],
                    EmployeeDocumentType::cases()
                ),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $request->input('sortField', 'expiry_date'),
                'sortDirection' => $sortDirection,
                'filters' => (array) $request->input('filters', []),
            ],
        ]);
    }

    public function store(EmployeeDocumentStoreRequest $request, AttachmentService $attachments, ActivityLogService $activityLog)
    {
        DB::transaction(function () use ($request, $attachments, $activityLog) {
            $validated = $this->normalizeDates($request->validated());
            unset($validated['documents']);

            $validated = $this->applyVerification($validated);

            $document = EmployeeDocument::create($validated);

            if ($request->hasFile('documents')) {
                $attachments->store($document, $request->file('documents'));
            }

            $activityLog->logCreate(
                reference: $document,
                module: 'employee_document',
                description: "Employee document ({$document->document_type?->value}) recorded.",
                newValues: $document->only(['employee_id', 'document_type', 'document_number', 'expiry_date']),
            );
        });

        return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('hr.document')]));
    }

    public function update(EmployeeDocumentUpdateRequest $request, EmployeeDocument $employeeDocument, AttachmentService $attachments, ActivityLogService $activityLog)
    {
        DB::transaction(function () use ($request, $employeeDocument, $attachments, $activityLog) {
            $before = $employeeDocument->only(['document_type', 'document_number', 'expiry_date', 'is_verified']);

            $validated = $this->normalizeDates($request->validated());
            unset($validated['documents']);

            $validated = $this->applyVerification($validated, $employeeDocument);

            $employeeDocument->update($validated);

            if ($request->hasFile('documents')) {
                $attachments->store($employeeDocument, $request->file('documents'));
            }

            $activityLog->logUpdate(
                reference: $employeeDocument,
                before: $before,
                after: $employeeDocument->only(array_keys($before)),
                module: 'employee_document',
                description: 'Employee document updated.',
            );
        });

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('hr.document')]));
    }

    public function destroy(Request $request, EmployeeDocument $employeeDocument)
    {
        $employeeDocument->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', ['resource' => __('hr.document')]));
    }

    public function restore(Request $request, EmployeeDocument $employeeDocument)
    {
        $this->authorize('update', $employeeDocument);

        $employeeDocument->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', ['resource' => __('hr.document')]));
    }

    public function forceDelete(Request $request, EmployeeDocument $employeeDocument)
    {
        $this->authorize('delete', $employeeDocument);

        app(DeletedRecordService::class)->forceDelete('employee_documents', (string) $employeeDocument->id);

        return redirect()->back()->with('success', __('general.permanently_deleted_successfully', ['resource' => __('hr.document')]));
    }

    /**
     * Who verified a document and when is recorded server-side, from the
     * authenticated user — never taken from the form, or anyone could claim a
     * document was checked by someone else.
     */
    private function applyVerification(array $validated, ?EmployeeDocument $existing = null): array
    {
        $wasVerified = (bool) ($existing?->is_verified ?? false);
        $nowVerified = (bool) ($validated['is_verified'] ?? false);

        if ($nowVerified && ! $wasVerified) {
            $validated['verified_by'] = auth()->id();
            $validated['verified_at'] = now();
        }

        if (! $nowVerified) {
            $validated['verified_by'] = null;
            $validated['verified_at'] = null;
        }

        return $validated;
    }

    private function normalizeDates(array $validated): array
    {
        foreach (['issue_date', 'expiry_date'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = $this->dateConversionService->toGregorian($validated[$field]);
            }
        }

        return $validated;
    }
}
