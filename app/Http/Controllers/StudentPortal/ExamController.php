<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamRemark;
use App\Services\AttendanceSummaryService;
use App\Services\ExamResultPdfService;
use App\Services\ExamResultService;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\View\View;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6, §7
 *
 * Read-only counterpart to TeacherPortal\ExamController — see
 * TeacherPortal\LessonPlanController's doc comment for the shape shared
 * across all four generic Phase 4 resources and all three portals.
 * Covers Exam only, not ExamMark. No store() — students never author
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

        return view('student.exams.index', ['exams' => $exams]);
    }

    /**
     * Also loads this student's own recorded mark for this exam (if
     * any) — view-only, read-only display per this task's own scoping
     * note. Queried directly off the Exam model's own marks() relation
     * filtered to $actor->id, the same direct-relation-query shape
     * ParentPortal\ExamController::show()'s own $marks lookup uses.
     */
    public function show(Request $request, ScopeService $scope, int $exam): View
    {
        $actor = $request->user();

        $resolved = $this->scopedFind($request, $scope, Exam::class, $exam, ['classSection.standard', 'classSection.section', 'subject']);

        if ($resolved === null) {
            abort(404);
        }

        $marks = $this->results->publishedMarksForStudent($resolved, $actor->id);

        $remark = ExamRemark::where('exam_id', $resolved->id)->where('student_id', $actor->id)->first();

        return view('student.exams.show', [
            'exam' => $resolved,
            'marks' => $marks,
            'weightedTotal' => $marks->isEmpty() ? null : $this->results->weightedTotal($marks),
            'remark' => $remark,
        ]);
    }

    public function download(
        Request $request,
        ScopeService $scope,
        ExamResultPdfService $pdf,
        AttendanceSummaryService $attendanceSummary,
        int $exam,
    ): Response {
        $actor = $request->user();

        $resolved = $this->scopedFind($request, $scope, Exam::class, $exam, ['subject']);

        if ($resolved === null) {
            abort(404);
        }

        $marks = $this->results->publishedMarksForStudent($resolved, $actor->id);

        if ($marks->isEmpty()) {
            abort(404);
        }

        $remark = ExamRemark::where('exam_id', $resolved->id)->where('student_id', $actor->id)->first();

        $pdfContent = $pdf->render(
            $resolved,
            $actor,
            $marks,
            $remark,
            $attendanceSummary->forStudent($resolved, $actor->id)
        );

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="result-' . $resolved->id . '-' . $actor->id . '.pdf"',
        ]);
    }
}
