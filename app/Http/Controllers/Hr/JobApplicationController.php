<?php

namespace App\Http\Controllers\Hr;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Exceptions\Hr\RecruitmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\JobApplicationStoreRequest;
use App\Http\Requests\Hr\JobApplicationUpdateRequest;
use App\Http\Resources\Hr\JobApplicationResource;
use App\Models\Administration\Province;
use App\Models\Hr\JobApplication;
use App\Models\Hr\JobOpening;
use App\Services\ActivityLogService;
use App\Services\AttachmentService;
use App\Services\DateConversionService;
use App\Services\Hr\RecruitmentService;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    public function __construct(
        private readonly DateConversionService $dateConversionService,
    ) {
        $this->authorizeResource(JobApplication::class, 'job_application');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $applications = JobApplication::query()
            ->with(['opening:id,title,code,branch_id', 'province:id,name', 'createdBy:id,name'])
            ->withCount('interviews')
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('applied_date', $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/JobApplications/Index', [
            'jobApplications' => JobApplicationResource::collection($applications),
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'applied_date',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function show(JobApplication $jobApplication)
    {
        $jobApplication->load([
            'opening:id,title,code,department_id,designation_id,employment_type,currency_id,branch_id',
            'province:id,name',
            'hiredEmployee:id,full_name,code,branch_id',
            'interviews.panelists.employee:id,full_name,branch_id',
            'interviews.panelists.user:id,name',
            'attachments',
            'createdBy:id,name',
        ]);

        return inertia('Hr/JobApplications/Show', [
            'jobApplication' => new JobApplicationResource($jobApplication),
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function store(JobApplicationStoreRequest $request, AttachmentService $attachments)
    {
        $application = JobApplication::create($request->validated() + [
            'status' => ApplicationStatus::Applied->value,
            'applied_date' => $request->input('applied_date') ?: now()->toDateString(),
        ]);

        // The CV. A plain attachment — no expiry date or verification state, so
        // it does not warrant its own table the way employee documents do.
        if ($request->hasFile('cv')) {
            $attachments->store($application, [$request->file('cv')]);
        }

        return redirect()->route('job-applications.show', $application)
            ->with('success', __('general.created_successfully', ['resource' => __('hr.job_application')]));
    }

    public function update(JobApplicationUpdateRequest $request, JobApplication $jobApplication, AttachmentService $attachments)
    {
        $jobApplication->update($request->validated());

        if ($request->hasFile('cv')) {
            $attachments->store($jobApplication, [$request->file('cv')]);
        }

        return redirect()->back()->with('success', __('general.updated_successfully', [
            'resource' => __('hr.job_application'),
        ]));
    }

    /**
     * Move a candidate along the pipeline.
     *
     * Hiring is NOT reachable here — it creates an Employee, so it has its own
     * endpoint rather than being a status change.
     */
    public function transition(Request $request, JobApplication $jobApplication, RecruitmentService $recruitment)
    {
        $this->authorize('update', $jobApplication);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
            'offered_salary' => ['nullable', 'numeric', 'min:0'],
            'offered_date' => ['nullable', 'date'],
        ]);

        $target = ApplicationStatus::tryFrom($validated['status']);

        if (! $target) {
            return redirect()->back()->with('error', __('hr.unknown_application_status'));
        }

        if (isset($validated['offered_date'])) {
            $validated['offered_date'] = $this->dateConversionService->toGregorian($validated['offered_date']);
        }

        try {
            $recruitment->transitionApplication($jobApplication, $target, $validated);
        } catch (RecruitmentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', __('hr.application_status_updated'));
    }

    /**
     * Turn a candidate into an employee.
     *
     * Goes through the Employee model, so EmployeeObserver creates the
     * companion ledger exactly as it would for a manually added employee.
     */
    public function hire(Request $request, JobApplication $jobApplication, RecruitmentService $recruitment, ActivityLogService $activityLog)
    {
        $this->authorize('update', $jobApplication);

        $validated = $request->validate([
            'joining_date' => ['required', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after:joining_date'],
            'department_id' => ['nullable', 'string', 'exists:departments,id'],
            'designation_id' => ['nullable', 'string', 'exists:designations,id'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
        ]);

        $validated['joining_date'] = $this->dateConversionService->toGregorian($validated['joining_date']);

        if (! empty($validated['probation_end_date'])) {
            $validated['probation_end_date'] = $this->dateConversionService
                ->toGregorian($validated['probation_end_date']);
        }

        try {
            $employee = $recruitment->hire($jobApplication, array_filter(
                $validated,
                fn ($value) => $value !== null
            ));
        } catch (RecruitmentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        $activityLog->logCreate(
            reference: $employee,
            module: 'employee',
            description: "{$employee->full_name} hired from application {$jobApplication->application_number}.",
            newValues: [
                'code' => $employee->code,
                'job_application_id' => $jobApplication->id,
            ],
        );

        return redirect()->route('employees.show', $employee)
            ->with('success', __('hr.candidate_hired'));
    }

    public function destroy(Request $request, JobApplication $jobApplication)
    {
        if (! $jobApplication->canBeDeleted()) {
            return redirect()->back()->with('error', $jobApplication->getDependencyMessage());
        }

        $jobApplication->delete();

        return redirect()->route('job-applications.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('hr.job_application')]));
    }

    public function restore(Request $request, JobApplication $jobApplication)
    {
        $this->authorize('update', $jobApplication);
        $jobApplication->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', [
            'resource' => __('hr.job_application'),
        ]));
    }

    private function filterOptions(): array
    {
        return [
            'jobOpenings' => JobOpening::query()
                ->orderBy('title')
                ->get(['id', 'code', 'title'])
                ->map(fn ($opening) => [
                    'id' => $opening->id,
                    'name' => trim($opening->code.' - '.$opening->title, ' -'),
                ]),
            // `provinces` has no `name` column — it stores name_en and name_fa.
            // Aliased to `name` so the picker is locale-correct rather than
            // always Dari.
            'provinces' => Province::query()
                ->orderBy(app()->getLocale() === 'en' ? 'name_en' : 'name_fa')
                ->get(['id', (app()->getLocale() === 'en' ? 'name_en' : 'name_fa').' as name']),
            'sources' => array_map(
                fn (ApplicationSource $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                ApplicationSource::cases()
            ),
            'statuses' => array_map(
                fn (ApplicationStatus $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                ApplicationStatus::cases()
            ),
        ];
    }
}
