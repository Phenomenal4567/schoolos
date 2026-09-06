<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Exam;
use App\Models\ExamComponent;
use App\Models\ExamRemark;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Repositories\ExamMarkRepository;
use App\Repositories\ExamRemarkRepository;
use App\Repositories\ExamRepository;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §5, §6, §7
 *
 * See TeacherPortal\LessonPlanController's doc comment for the shape
 * shared across all four generic Phase 4 resource controllers — not
 * repeated here.
 *
 * recordMark() (this pass's addition) is ExamMark's write path — see
 * ExamMarkRepository::record()'s own doc comment for the F29 fix it
 * enforces. No route parameter (it upserts by exam_id + student_id from
 * the request body, it doesn't resolve one by id), exempt from row 8's
 * lint for the same reason *.store above is.
 */
class ExamController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $exams = $this->scopedIndex($request, $scope, Exam::class, ['classSection.standard', 'classSection.section', 'subject']);

        // View-only data for the "New exam" form below — see
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

        return view('teacher.exams.index', [
            'exams' => $exams,
            'classSections' => $classSections,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Also loads the exam's class_section roster and any already-recorded
     * marks for the marks-entry form below — view-only, same
     * relationshipScope(StudentEnrollment) + direct-relation-query shape
     * AttendanceController::show() already establishes for a roster next
     * to a per-row write form.
     */
    public function show(Request $request, ScopeService $scope, int $exam): View
    {
        $actor = $request->user();

        $resolved = $this->scopedFind($request, $scope, Exam::class, $exam, ['classSection.standard', 'classSection.section', 'subject']);

        if ($resolved === null) {
            abort(404);
        }

        $enrollments = $scope
            ->relationshipScope(StudentEnrollment::query(), $actor, StudentEnrollment::class)
            ->where('class_section_id', $resolved->class_section_id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy(fn ($enrollment) => $enrollment->student->name ?? '')
            ->values();

        $existingMarks = $resolved->marks()->get();
        $components = ExamComponent::where('school_id', $actor->school_id)->orderBy('name')->get();
        $existingRemarks = ExamRemark::where('exam_id', $resolved->id)->get()->keyBy('student_id');

        return view('teacher.exams.show', [
            'exam' => $resolved,
            'enrollments' => $enrollments,
            'existingMarks' => $existingMarks,
            'components' => $components,
            'existingRemarks' => $existingRemarks,
        ]);
    }

    /**
     * The "New exam" HTML form's own submit target — see
     * TeacherPortal\TimetableController::store()'s doc comment for why
     * both the caught InvalidArgumentException and the success path now
     * branch on wantsJson().
     */
    public function store(Request $request, ScopeService $scope, ExamRepository $repository): Response
    {
        $actor = $request->user();

        $data = $request->validate([
            'class_section_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'exam_date' => ['required', 'date'],
        ]);

        try {
            $exam = $repository->create(
                $actor->school_id,
                $data['class_section_id'],
                $data['subject_id'],
                $data['name'],
                $data['exam_date'],
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
            return response()->json(['data' => $exam], 201);
        }

        return redirect()->route('teacher.exams.index')->with('status', 'Exam created.');
    }

    /**
     * One form per enrolled student on the exam show page submits here —
     * a plain `<form method="POST">`, no route parameter (this upserts
     * by exam_id + student_id from the request body, per
     * ExamMarkRepository::record()'s own doc comment). Browser callers
     * get back()->with('status') the same way every other web-form write
     * action in this app does; fetch()/API callers keep the original
     * JSON 201/422 contract.
     */
    public function recordMark(Request $request, ExamMarkRepository $repository): Response
    {
        $actor = $request->user();

        $data = $request->validate([
            'exam_id' => ['required', 'integer'],
            'exam_component_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
            'marks_obtained' => ['required', 'numeric', 'min:0'],
            'max_marks' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $mark = $repository->record(
                $actor->school_id,
                $data['exam_id'],
                $data['exam_component_id'],
                $data['student_id'],
                (float) $data['marks_obtained'],
                (float) $data['max_marks'],
                $actor
            );
        } catch (TeacherNotAssignedToSectionFailure) {
            abort(404);
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['exam_id' => $e->getMessage()]], 422);
            }

            return back()->withErrors(['exam_id' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $mark], 201);
        }

        return back()->with('status', 'Mark recorded.');
    }

    /**
     * The class-teacher-remark counterpart to recordMark() above — same
     * no-route-parameter shape (upserts by exam_id + student_id from the
     * request body), same exception handling, per
     * ExamRemarkRepository::recordClassTeacherRemark()'s own doc
     * comment.
     */
    public function recordRemark(Request $request, ExamRemarkRepository $repository): Response
    {
        $actor = $request->user();

        $data = $request->validate([
            'exam_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
            'remark' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $remark = $repository->recordClassTeacherRemark(
                $actor->school_id,
                $data['exam_id'],
                $data['student_id'],
                $data['remark'],
                $actor
            );
        } catch (TeacherNotAssignedToSectionFailure) {
            abort(404);
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['exam_id' => $e->getMessage()]], 422);
            }

            return back()->withErrors(['exam_id' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $remark], 201);
        }

        return back()->with('status', 'Remark saved.');
    }
}
