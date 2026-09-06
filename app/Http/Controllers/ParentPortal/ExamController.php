<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamRemark;
use App\Models\StudentParentLink;
use App\Models\User;
use App\Services\AttendanceSummaryService;
use App\Services\ExamResultPdfService;
use App\Services\ExamResultService;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6, §7
 *
 * Read-only counterpart to TeacherPortal\ExamController — see
 * TeacherPortal\LessonPlanController's doc comment for the shape shared
 * across all four generic Phase 4 resources and all three portals.
 * Covers Exam only, not ExamMark. No store() — parents never author
 * exams.
 */
class ExamController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function __construct(private readonly ExamResultService $results)
    {
    }

    public function index(Request $request, ScopeService $scope): View
    {
        $exams = $this->scopedIndex($request, $scope, Exam::class, ['classSection.standard', 'classSection.section', 'subject']);

        return view('parent.exams.index', ['exams' => $exams]);
    }

    /**
     * Also loads any recorded ExamMark rows for this parent's own linked
     * children on this exam — view-only, read-only display per this
     * task's own scoping note ("Parent/Student: show recorded marks ...
     * once available (read-only)"). Queried directly off the Exam
     * model's own marks() relation (see that model's doc comment)
     * filtered to this parent's linked student ids, the same
     * direct-relation-query shape AnnouncementController::show()'s
     * $isRead lookup already uses for a view-only presentational check
     * alongside the main scoped resource.
     */
    public function show(Request $request, ScopeService $scope, int $exam): View
    {
        $actor = $request->user();

        $resolved = $this->scopedFind($request, $scope, Exam::class, $exam, ['classSection.standard', 'classSection.section', 'subject']);

        if ($resolved === null) {
            abort(404);
        }

        $linkedStudentIds = StudentParentLink::where('parent_id', $actor->id)
            ->where('status', 'active')
            ->pluck('student_id');

        $marks = $resolved->marks()
            ->whereIn('student_id', $linkedStudentIds)
            ->where('status', ExamMark::STATUS_PUBLISHED)
            ->with('component')
            ->with('student')
            ->get();

        $remarksByStudent = ExamRemark::where('exam_id', $resolved->id)
            ->whereIn('student_id', $linkedStudentIds)
            ->get()
            ->keyBy('student_id');

        return view('parent.exams.show', [
            'exam' => $resolved,
            'marks' => $marks,
            'weightedTotals' => $marks->groupBy('student_id')->map(fn ($studentMarks) => $this->results->weightedTotal($studentMarks)),
            'remarksByStudent' => $remarksByStudent,
        ]);
    }

    public function download(
        Request $request,
        ScopeService $scope,
        ExamResultPdfService $pdf,
        AttendanceSummaryService $attendanceSummary,
        int $exam,
        int $student,
    ): Response {
        $actor = $request->user();

        $resolved = $this->scopedFind($request, $scope, Exam::class, $exam, ['subject']);

        if ($resolved === null) {
            abort(404);
        }

        $linked = StudentParentLink::where('parent_id', $actor->id)
            ->where('student_id', $student)
            ->where('status', 'active')
            ->exists();

        if (! $linked) {
            abort(404);
        }

        $studentUser = User::where('school_id', $actor->school_id)->findOrFail($student);
        $marks = $this->results->publishedMarksForStudent($resolved, $student);

        if ($marks->isEmpty()) {
            abort(404);
        }

        $remark = ExamRemark::where('exam_id', $resolved->id)->where('student_id', $student)->first();

        $pdfContent = $pdf->render(
            $resolved,
            $studentUser,
            $marks,
            $remark,
            $attendanceSummary->forStudent($resolved, $student)
        );

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="result-' . $resolved->id . '-' . $student . '.pdf"',
        ]);
    }
}
