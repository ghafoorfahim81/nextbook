<?php

namespace App\Http\Controllers\Hr;

use App\Enums\EmploymentType;
use App\Enums\JobOpeningStatus;
use App\Exceptions\Hr\RecruitmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\JobOpeningStoreRequest;
use App\Http\Requests\Hr\JobOpeningUpdateRequest;
use App\Http\Resources\Hr\JobApplicationResource;
use App\Http\Resources\Hr\JobOpeningResource;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Administration\Designation;
use App\Models\Hr\JobOpening;
use App\Services\Hr\RecruitmentService;
use Illuminate\Http\Request;

class JobOpeningController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(JobOpening::class, 'job_opening');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $openings = JobOpening::query()
            ->with([
                'department:id,name,branch_id',
                'designation:id,name,branch_id',
                'currency:id,code',
                'hiringManager:id,full_name,branch_id',
                'createdBy:id,name',
            ])
            ->withCount('applications')
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('posted_date', $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/JobOpenings/Index', [
            'jobOpenings' => JobOpeningResource::collection($openings),
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'posted_date',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function show(JobOpening $jobOpening)
    {
        $jobOpening->load([
            'department:id,name,branch_id',
            'designation:id,name,branch_id',
            'currency:id,code',
            'hiringManager:id,full_name,branch_id',
            'createdBy:id,name',
        ])->loadCount('applications');

        $applications = $jobOpening->applications()
            ->with(['opening:id,title,code,branch_id'])
            ->withCount('interviews')
            ->orderByDesc('score')
            ->orderBy('full_name')
            ->get();

        return inertia('Hr/JobOpenings/Show', [
            'jobOpening' => new JobOpeningResource($jobOpening),
            'applications' => JobApplicationResource::collection($applications),
        ]);
    }

    public function store(JobOpeningStoreRequest $request)
    {
        $opening = JobOpening::create($request->validated() + [
            'status' => JobOpeningStatus::Draft->value,
        ]);

        return redirect()->route('job-openings.show', $opening)
            ->with('success', __('general.created_successfully', ['resource' => __('hr.job_opening')]));
    }

    public function update(JobOpeningUpdateRequest $request, JobOpening $jobOpening)
    {
        $jobOpening->update($request->validated());

        return redirect()->back()->with('success', __('general.updated_successfully', [
            'resource' => __('hr.job_opening'),
        ]));
    }

    public function transition(Request $request, JobOpening $jobOpening, RecruitmentService $recruitment)
    {
        $this->authorize('update', $jobOpening);

        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $target = JobOpeningStatus::tryFrom($validated['status']);

        if (! $target) {
            return redirect()->back()->with('error', __('hr.unknown_opening_status'));
        }

        try {
            $recruitment->transitionOpening($jobOpening, $target);
        } catch (RecruitmentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', __('hr.opening_status_updated'));
    }

    public function destroy(Request $request, JobOpening $jobOpening)
    {
        if (! $jobOpening->canBeDeleted()) {
            return redirect()->back()->with('error', $jobOpening->getDependencyMessage());
        }

        $jobOpening->delete();

        return redirect()->route('job-openings.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('hr.job_opening')]));
    }

    public function restore(Request $request, JobOpening $jobOpening)
    {
        $this->authorize('update', $jobOpening);
        $jobOpening->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', [
            'resource' => __('hr.job_opening'),
        ]));
    }

    private function filterOptions(): array
    {
        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
            'employmentTypes' => array_map(
                fn (EmploymentType $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                EmploymentType::cases()
            ),
            'statuses' => array_map(
                fn (JobOpeningStatus $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                JobOpeningStatus::cases()
            ),
        ];
    }
}
