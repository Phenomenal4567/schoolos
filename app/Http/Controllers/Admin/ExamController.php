<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamComponent;
use App\Models\ExamMark;
use App\Models\ExamRemark;
use App\Repositories\ExamRemarkRepository;
use App\Services\ExamResultService;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $exams = $scope->tenantScope(Exam::query(), $actor)
            ->with(['classSection.standard', 'classSection.section', 'subject', 'marks'])
            ->orderByDesc('exam_date')
            ->get();

        $components = ExamComponent::where('school_id', $actor->school_id)
            ->orderBy('name')
            ->get();

        return view('admin.exams.index', [
            'exams' => $exams,
            'components' => $components,
        ]);
    }

    public function show(Request $request, ScopeService $scope, ExamResultService $results, int $exam): View
    {
        $actor = $request->user();
        $resolved = $scope->tenantScope(Exam::query(), $actor)
            ->with(['classSection.standard', 'classSection.section', 'subject', 'marks.component', 'marks.student'])
            ->findOrFail($exam);

        $marksByStudent = $resolved->marks
            ->groupBy('student_id')
            ->map(fn ($marks) => [
                'student' => $marks->first()->student,
                'marks' => $marks,
                'weighted_total' => $results->weightedTotal($marks),
            ]);

        $remarksByStudent = ExamRemark::where('exam_id', $resolved->id)->get()->keyBy('student_id');

        return view('admin.exams.show', [
            'exam' => $resolved,
            'marksByStudent' => $marksByStudent,
            'remarksByStudent' => $remarksByStudent,
        ]);
    }

    /**
     * The proprietor-remark write path — see
     * ExamRemarkRepository::recordProprietorRemark()'s own doc comment
     * for why this has no teacher-assignment check the way
     * TeacherPortal\ExamController::recordRemark() does. Carries a route
     * parameter for $student (unlike that method's body-only shape)
     * since this action already resolves $exam by route parameter, same
     * as updateMark()/review()/publish() above.
     */
    public function updateRemark(
        Request $request,
        ScopeService $scope,
        ExamRemarkRepository $repository,
        int $exam,
        int $student,
    ): RedirectResponse {
        $actor = $request->user();
        $resolved = $scope->tenantScope(Exam::query(), $actor)->findOrFail($exam);

        $data = $request->validate([
            'remark' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $remark = $repository->recordProprietorRemark($actor->school_id, $resolved->id, $student, $data['remark'], $actor);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['remark' => $e->getMessage()]);
        }

        AuditLog::create([
            'school_id' => $actor->school_id,
            'actor_id' => $actor->id,
            'action' => 'exam_remark.proprietor_updated',
            'entity_type' => ExamRemark::class,
            'entity_id' => $remark->id,
            'before_state' => null,
            'after_state' => ['proprietor_remark' => $remark->proprietor_remark],
        ]);

        return back()->with('status', 'Remark saved.');
    }

    public function updateMark(Request $request, ScopeService $scope, int $exam, int $mark): RedirectResponse
    {
        $actor = $request->user();
        $resolved = $scope->tenantScope(Exam::query(), $actor)->findOrFail($exam);
        $examMark = $resolved->marks()->findOrFail($mark);

        $data = $request->validate([
            'marks_obtained' => ['required', 'numeric', 'min:0'],
            'max_marks' => ['required', 'numeric', 'min:0.01'],
        ]);

        $before = [
            'marks_obtained' => (string) $examMark->marks_obtained,
            'max_marks' => (string) $examMark->max_marks,
        ];

        $examMark->update([
            'marks_obtained' => (float) $data['marks_obtained'],
            'max_marks' => (float) $data['max_marks'],
        ]);

        AuditLog::create([
            'school_id' => $actor->school_id,
            'actor_id' => $actor->id,
            'action' => 'exam_mark.updated',
            'entity_type' => ExamMark::class,
            'entity_id' => $examMark->id,
            'before_state' => $before,
            'after_state' => [
                'marks_obtained' => (string) $examMark->marks_obtained,
                'max_marks' => (string) $examMark->max_marks,
            ],
        ]);

        return back()->with('status', 'Mark updated.');
    }

    public function review(Request $request, ScopeService $scope, int $exam, int $mark): RedirectResponse
    {
        $actor = $request->user();
        $resolved = $scope->tenantScope(Exam::query(), $actor)->findOrFail($exam);
        $examMark = $resolved->marks()->findOrFail($mark);

        $examMark->update(['status' => ExamMark::STATUS_REVIEWED]);

        return back()->with('status', 'Mark reviewed.');
    }

    public function publish(Request $request, ScopeService $scope, int $exam, int $mark): RedirectResponse
    {
        $actor = $request->user();
        $resolved = $scope->tenantScope(Exam::query(), $actor)->findOrFail($exam);
        $examMark = $resolved->marks()->findOrFail($mark);

        if ($examMark->status !== ExamMark::STATUS_REVIEWED) {
            return back()->withErrors(['status' => 'Only reviewed marks can be published.']);
        }

        $examMark->update(['status' => ExamMark::STATUS_PUBLISHED]);

        return back()->with('status', 'Mark published.');
    }
}
