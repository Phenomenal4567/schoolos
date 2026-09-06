<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Exceptions\Academic\UnauthorizedGradingFailure;
use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassSection;
use App\Models\Subject;
use App\Repositories\AssignmentRepository;
use App\Repositories\AssignmentSubmissionRepository;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §5, §6
 *
 * See TeacherPortal\LessonPlanController's doc comment for the shape
 * shared across all four generic Phase 4 resource controllers — not
 * repeated here.
 *
 * grade() (this pass's addition) is the write side of
 * AssignmentSubmission this controller previously had none of — see
 * AssignmentSubmissionRepository::grade()'s own doc comment for the F29
 * fix it enforces. {submission} is an existing row resolved by ID, so
 * this route carries 'scope.checked' the same as show() above.
 */
class AssignmentController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $assignments = $this->scopedIndex($request, $scope, Assignment::class, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        // View-only data for the "New assignment" form below — see
        // TeacherPortal\TimetableController::index()'s own doc comment
        // for the identical reasoning.
        $classSections = $scope
            ->relationshipScope(
                $scope->tenantScope(ClassSection::query(), $actor),
                $actor,
                ClassSection::class
            )
            ->with(['standard', 'section'])
            ->get();

        $subjects = $scope->tenantScope(Subject::query(), $actor)->orderBy('name')->get();

        return view('teacher.assignments.index', [
            'assignments' => $assignments,
            'classSections' => $classSections,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Also loads this assignment's own submissions for the "Grade" list
     * below — view-only, tenant/relationship-scoped through
     * AssignmentSubmission the identical way ScopeService::
     * teacherRelationshipScope()'s assignment_id branch resolves grade()
     * itself (see AssignmentSubmissionRepository's doc comment on that
     * branch), rather than reading $resolved->submissions directly
     * unscoped. Doesn't change which submissions exist — only which of
     * them this view is allowed to display, mirroring every other
     * "view-only lookup alongside the main scoped resource" query
     * elsewhere in this bundle (e.g. AnnouncementController::index()'s
     * $readIds).
     */
    public function show(Request $request, ScopeService $scope, int $assignment): View
    {
        $actor = $request->user();

        $resolved = $this->scopedFind($request, $scope, Assignment::class, $assignment, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        if ($resolved === null) {
            abort(404);
        }

        $submissions = $scope
            ->relationshipScope(
                $scope->tenantScope(AssignmentSubmission::query(), $actor),
                $actor,
                AssignmentSubmission::class
            )
            ->where('assignment_id', $resolved->id)
            ->with('student')
            ->get()
            ->sortBy(fn ($submission) => $submission->student->name ?? '')
            ->values();

        return view('teacher.assignments.show', [
            'assignment' => $resolved,
            'submissions' => $submissions,
        ]);
    }

    /**
     * The "New assignment" HTML form's own submit target — see
     * TeacherPortal\TimetableController::store()'s doc comment for why
     * both the caught InvalidArgumentException and the success path now
     * branch on wantsJson().
     */
    public function store(Request $request, ScopeService $scope, AssignmentRepository $repository): Response
    {
        $actor = $request->user();

        $data = $request->validate([
            'class_section_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        try {
            $assignment = $repository->create(
                $actor->school_id,
                $data['class_section_id'],
                $data['subject_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['due_date'] ?? null,
                $actor
            );
        } catch (TeacherNotAssignedToSectionFailure) {
            abort(404);
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['class_section_id' => $e->getMessage()]], 422);
            }

            return back()->withErrors(['class_section_id' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $assignment], 201);
        }

        return redirect()->route('teacher.assignments.index')->with('status', 'Assignment created.');
    }

    /**
     * The per-submission "Grade" form's own submit target, on the
     * assignment show page — a plain `<form method="POST">` per
     * submission row, so a browser caller gets back()->with('status')
     * the same way every other web-form write action in this app does,
     * while any fetch()/API caller keeps the original JSON contract.
     */
    public function grade(Request $request, AssignmentSubmissionRepository $repository, int $submission): Response
    {
        $actor = $request->user();

        $data = $request->validate([
            'obtained_marks' => ['required', 'numeric', 'min:0'],
            'comments' => ['nullable', 'string'],
        ]);

        try {
            $graded = $repository->grade($submission, $actor, (float) $data['obtained_marks'], $data['comments'] ?? null);
        } catch (UnauthorizedGradingFailure) {
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $graded]);
        }

        return back()->with('status', 'Submission graded.');
    }
}
