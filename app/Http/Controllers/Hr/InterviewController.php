<?php

namespace App\Http\Controllers\Hr;

use App\Enums\InterviewRecommendation;
use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Exceptions\Hr\RecruitmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\InterviewFeedbackRequest;
use App\Http\Requests\Hr\InterviewStoreRequest;
use App\Http\Resources\Hr\InterviewResource;
use App\Models\Hr\Interview;
use App\Models\Hr\InterviewPanelist;
use App\Models\Hr\JobApplication;
use App\Services\Hr\RecruitmentService;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Interview::class, 'interview');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        $interviews = Interview::query()
            ->with([
                'application:id,full_name,application_number,job_opening_id,branch_id',
                'application.opening:id,title,branch_id',
                'panelists.employee:id,full_name,branch_id',
                'panelists.user:id,name',
                'createdBy:id,name',
            ])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('scheduled_at', $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/Interviews/Index', [
            'interviews' => InterviewResource::collection($interviews),
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'scheduled_at',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function store(InterviewStoreRequest $request, RecruitmentService $recruitment)
    {
        $validated = $request->validated();

        $application = JobApplication::query()->findOrFail($validated['job_application_id']);

        try {
            $recruitment->scheduleInterview(
                $application,
                $validated,
                $validated['panelists'] ?? []
            );
        } catch (RecruitmentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', __('hr.interview_scheduled'));
    }

    public function update(Request $request, Interview $interview)
    {
        $validated = $request->validate([
            'interview_type' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'status' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],
        ]);

        // Rescheduling is a status in its own right, so the pipeline can show
        // that a slot moved rather than silently changing the date.
        if (! empty($validated['scheduled_at'])
            && $interview->statusEnum() === InterviewStatus::Scheduled
            && $interview->scheduled_at?->toIso8601String() !== $validated['scheduled_at']
            && empty($validated['status'])) {
            $validated['status'] = InterviewStatus::Rescheduled->value;
        }

        $interview->update(array_filter($validated, fn ($value) => $value !== null));

        return redirect()->back()->with('success', __('general.updated_successfully', [
            'resource' => __('hr.interview'),
        ]));
    }

    /**
     * One panelist's verdict.
     *
     * Recorded per person rather than merged into the interview: the
     * disagreement between two interviewers is the most useful thing in the
     * record, and a shared box loses it.
     */
    public function feedback(
        InterviewFeedbackRequest $request,
        Interview $interview,
        InterviewPanelist $panelist,
        RecruitmentService $recruitment
    ) {
        $this->authorize('update', $interview);

        // Both models are resolved independently from the URL, so nothing ties
        // the panelist to the interview in the path. Without this, feedback for
        // one candidate could be written onto another interview's panelist by
        // pairing mismatched ids — and the authorize() above would still pass,
        // because it only checks the interview.
        if ($panelist->interview_id !== $interview->id) {
            abort(404);
        }

        try {
            $recruitment->submitFeedback($panelist, $request->validated());
        } catch (RecruitmentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', __('hr.feedback_recorded'));
    }

    public function complete(Request $request, Interview $interview, RecruitmentService $recruitment)
    {
        $this->authorize('update', $interview);

        $validated = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'recommendation' => ['nullable', 'string'],
            'feedback' => ['nullable', 'string'],
        ]);

        $recruitment->completeInterview(
            $interview,
            array_filter($validated, fn ($value) => $value !== null)
        );

        return redirect()->back()->with('success', __('hr.interview_completed'));
    }

    public function destroy(Request $request, Interview $interview)
    {
        $interview->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', [
            'resource' => __('hr.interview'),
        ]));
    }

    public function restore(Request $request, Interview $interview)
    {
        $this->authorize('update', $interview);
        $interview->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', [
            'resource' => __('hr.interview'),
        ]));
    }

    private function filterOptions(): array
    {
        return [
            'interviewTypes' => array_map(
                fn (InterviewType $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                InterviewType::cases()
            ),
            'statuses' => array_map(
                fn (InterviewStatus $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                InterviewStatus::cases()
            ),
            'recommendations' => array_map(
                fn (InterviewRecommendation $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                InterviewRecommendation::cases()
            ),
        ];
    }
}
