<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\LessonPlan;
use App\Models\Subject;
use App\Repositories\LessonPlanRepository;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §5, §6
 *
 * The read + teacher-create surface for the four generic Phase 4
 * resources (this controller, TimetableController, AssignmentController,
 * ExamController — all four share this exact shape, so it's documented
 * once here rather than four times):
 *
 * - index()/show() resolve through
 *   ScopeService::tenantScope()->relationshipScope() via
 *   ResolvesScopedAcademicResource (see that trait's doc comment) —
 *   identical resolution AttendanceController::index()/show() already
 *   establishes, just against a different model class. index() is
 *   id-less (no 'scope.checked' needed); show() resolves one row by
 *   route parameter and carries 'scope.checked' at the route level (see
 *   routes/web.php), aborting 404 — never 403 — on a miss, the same
 *   discipline AttendanceController::show() documents.
 * - store() delegates the actual "may this teacher author content for
 *   this class_section_id" gate to the matching repository's create()
 *   method (LessonPlanRepository, TimetableRepository, AssignmentRepository,
 *   ExamRepository — see each's own doc comment for
 *   assertTeacherAssignedToSection()), and converts a caught
 *   TeacherNotAssignedToSectionFailure into abort(404), never a 403 —
 *   same reasoning.
 * - Responses are JSON, not Blade views, across all four of these
 *   controllers (and their ParentPortal/StudentPortal read-only
 *   counterparts) — a deliberate scope decision for this pass, unlike
 *   AttendanceController's HTML "UI vertical slice." This task's own
 *   brief describes a read+create surface for four resource types across
 *   three portals (24 index/show actions total); building and testing
 *   Blade templates for all of them is front-end work orthogonal to the
 *   actual deliverable this pass is scoped to (authorization/scoping
 *   correctness, matching Phase 4/5's test-gate shape), and each new
 *   view is untested surface area this pass has no test coverage for.
 *   Existing store()-only admin routes (SubjectController,
 *   EnrollmentController) already establish that not every write path in
 *   this bundle has a matching bespoke view — this extends the same
 *   restraint to these four resources' reads too. Flagged explicitly in
 *   this task's own report as a scope decision worth confirming, not an
 *   oversight.
 *
 * update()/destroy() are out of scope for this pass on all four
 * controllers — see this task's own "read + teacher-create surface"
 * scoping note.
 */
class LessonPlanController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $lessonPlans = $this->scopedIndex($request, $scope, LessonPlan::class, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        // View-only data for the "New lesson plan" form below — see
        // TeacherPortal\TimetableController::index()'s own doc comment
        // for the identical reasoning (offering valid class_section_id/
        // subject_id options for store(), not changing what store()
        // itself checks).
        $classSections = $scope
            ->relationshipScope(
                $scope->tenantScope(ClassSection::query(), $actor),
                $actor,
                ClassSection::class
            )
            ->with(['standard', 'section'])
            ->get();

        $subjects = $scope->tenantScope(Subject::query(), $actor)->orderBy('name')->get();

        return view('teacher.lesson-plans.index', [
            'lessonPlans' => $lessonPlans,
            'classSections' => $classSections,
            'subjects' => $subjects,
        ]);
    }

    public function show(Request $request, ScopeService $scope, int $lessonPlan): View
    {
        $resolved = $this->scopedFind($request, $scope, LessonPlan::class, $lessonPlan, ['classSection.standard', 'classSection.section', 'subject', 'teacher', 'reviewedBy']);

        if ($resolved === null) {
            abort(404);
        }

        return view('teacher.lesson-plans.show', ['lessonPlan' => $resolved]);
    }

    /**
     * This is the "New lesson plan" HTML form's own submit target (a
     * plain `<form method="POST">`, no fetch()) — see
     * TeacherPortal\TimetableController::store()'s doc comment for why
     * both the caught InvalidArgumentException and the success path now
     * branch on wantsJson() rather than always returning JSON: a browser
     * form submit needs withErrors()/withInput() and a redirect the way
     * every other web-form store() in this app behaves, while any
     * fetch()/API caller keeps the original JSON 201/422 contract.
     */
    public function store(Request $request, ScopeService $scope, LessonPlanRepository $repository): Response
    {
        $actor = $request->user();

        $data = $request->validate([
            'class_section_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        try {
            $lessonPlan = $repository->create(
                $actor->school_id,
                $data['class_section_id'],
                $data['subject_id'],
                $data['title'],
                $data['content'],
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
            return response()->json(['data' => $lessonPlan], 201);
        }

        return redirect()->route('teacher.lesson-plans.index')->with('status', 'Lesson plan created.');
    }
}
