<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Exceptions\Attendance\StudentNotEnrolledFailure;
use App\Http\Controllers\Controller;
use App\Models\StudentEnrollment;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceTopic;
use App\Models\TimetableSlot;
use App\Repositories\SubjectAttendanceRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
 *
 * Mirrors TeacherPortal\AttendanceController's index()/show()/store()
 * shape (see that class's doc comment for the 404-not-403 resolution
 * discipline every method here follows identically) with one difference:
 * where AttendanceController resolves $classSection through
 * ScopeService::relationshipScope() — which grants any teacher assigned
 * to (or homeroom of) a class section access to that section's class
 * attendance — subject attendance is narrower on purpose. A class can
 * have several subject teachers; only the teacher actually timetabled to
 * teach *that* period should be able to record what happened in it, so
 * every TimetableSlot lookup below adds an explicit
 * ->where('teacher_id', $actor->id) on top of ScopeService::tenantScope(),
 * rather than reusing relationshipScope()'s broader "assigned to the
 * class" grant.
 */
class SubjectAttendanceController extends Controller
{
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $slots = $scope->tenantScope(TimetableSlot::query(), $actor)
            ->where('teacher_id', $actor->id)
            ->with(['classSection.standard', 'classSection.section', 'subject'])
            ->orderBy('day_of_week')
            ->orderBy('period_number')
            ->get();

        return view('teacher.subject-attendance.index', [
            'slots' => $slots,
        ]);
    }

    /**
     * One timetable slot's roster for one date, with each student's
     * current subject-attendance status (if already recorded) and that
     * date's topic taught (if already saved) — same "mark vs. correct"
     * per-row shape AttendanceController::show() documents.
     */
    public function show(Request $request, ScopeService $scope, int $timetableSlot): View
    {
        $actor = $request->user();

        $slot = $scope->tenantScope(TimetableSlot::query(), $actor)
            ->where('teacher_id', $actor->id)
            ->with(['classSection.standard', 'classSection.section', 'subject'])
            ->find($timetableSlot);

        if ($slot === null) {
            abort(404);
        }

        $date = $request->query('date');
        $date = (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : now()->toDateString();

        $enrollments = $scope
            ->relationshipScope(StudentEnrollment::query(), $actor, StudentEnrollment::class)
            ->where('class_section_id', $slot->class_section_id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy(fn ($enrollment) => $enrollment->student->name ?? '')
            ->values();

        $records = $scope
            ->relationshipScope(SubjectAttendanceRecord::query(), $actor, SubjectAttendanceRecord::class)
            ->where('timetable_slot_id', $slot->id)
            ->where('date', $date)
            ->withCount('corrections')
            ->get()
            ->keyBy('student_id');

        $topic = SubjectAttendanceTopic::where('timetable_slot_id', $slot->id)
            ->where('date', $date)
            ->first();

        return view('teacher.subject-attendance.show', [
            'slot' => $slot,
            'date' => $date,
            'enrollments' => $enrollments,
            'records' => $records,
            'topic' => $topic,
        ]);
    }

    public function store(Request $request, ScopeService $scope, SubjectAttendanceRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'timetable_slot_id' => ['required', 'integer'],
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $slot = $scope->tenantScope(TimetableSlot::query(), $actor)
            ->where('teacher_id', $actor->id)
            ->find($data['timetable_slot_id']);

        if ($slot === null) {
            abort(404);
        }

        try {
            $repository->mark(
                $actor->school_id,
                $slot->academic_year_id,
                $slot->id,
                $data['student_id'],
                $data['date'],
                $data['status'],
                $actor,
                $data['note'] ?? null,
                $data['reason'] ?? null
            );
        } catch (StudentNotEnrolledFailure $e) {
            return back()->withErrors(['student_id' => $e->getMessage()])->withInput();
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Subject attendance recorded.');
    }

    public function topic(Request $request, ScopeService $scope, SubjectAttendanceRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'timetable_slot_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'topic' => ['required', 'string', 'max:2000'],
        ]);

        $slot = $scope->tenantScope(TimetableSlot::query(), $actor)
            ->where('teacher_id', $actor->id)
            ->find($data['timetable_slot_id']);

        if ($slot === null) {
            abort(404);
        }

        $repository->recordTopic($actor->school_id, $slot->id, $data['date'], $data['topic'], $actor);

        return back()->with('status', 'Topic taught saved.');
    }
}
