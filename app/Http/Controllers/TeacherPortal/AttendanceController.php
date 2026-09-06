<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Exceptions\Attendance\StudentNotEnrolledFailure;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\ClassSection;
use App\Models\StudentEnrollment;
use App\Repositories\AttendanceRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

/**
 * Design ref: 12-schoolos-architecture.md §3a, 14-schoolos-implementation-plan.md §4
 *
 * First controller/route calling AttendanceRepository::mark() — same
 * "repository built and tested before any route called it" precedent
 * ClassSectionRepository/EnrollmentRepository/ParentLinkRepository all
 * established. Resolves $classSection through
 * tenantScope()->relationshipScope() *before* touching it, matching
 * DashboardController's own "never fetch-then-check" discipline — a
 * teacher supplying a class_section_id they're not assigned to (F18's
 * exact shape) resolves to a 404, identically to "doesn't exist," never
 * a 403 that would confirm the id is valid for someone else's class.
 *
 * index()/show() below are this pass's addition — the UI vertical slice
 * needs a GET surface to reach store(), which previously had none.
 * Both follow store()'s own resolution discipline exactly: index() has
 * no route parameter (an id-less listing, like
 * ParentPortal\DashboardController::index()); show() resolves
 * $classSection the identical tenantScope()->relationshipScope() way
 * store() does, 404-not-403, and carries 'scope.checked' at the route
 * level (see routes/web.php) since it resolves one record by route
 * parameter, per Phase1TestGateTest's row 8 lint. Neither method writes
 * anything — store() remains the only write path, unmodified.
 */
class AttendanceController extends Controller
{
    /**
     * The teacher's own class sections, as an entry point into Attendance.
     * Same relationshipScope() resolution as store()'s $classSection
     * lookup, just not narrowed to a single id.
     */
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $classSections = $scope
            ->relationshipScope(
                $scope->tenantScope(ClassSection::query(), $actor),
                $actor,
                ClassSection::class
            )
            ->with(['standard', 'section'])
            ->withCount(['studentEnrollments' => fn ($q) => $q->where('status', 'active')])
            ->get();

        return view('teacher.attendance.index', [
            'classSections' => $classSections,
        ]);
    }

    /**
     * A single class section's roster for one date/session, with each
     * student's current attendance status (if already recorded) so the
     * view can distinguish "mark" from "correct" per row — store() only
     * accepts one student_id per call, so this page's forms submit one
     * student at a time, matching that contract rather than inventing a
     * batch endpoint.
     *
     * $date/$session are read from the query string for display only
     * (which roster/date to *look at*) — they carry no authorization
     * weight themselves and default to today/morning when absent or
     * malformed, rather than erroring on a read-only view.
     */
    public function show(Request $request, ScopeService $scope, int $classSection): View
    {
        $actor = $request->user();

        $resolvedClassSection = $scope
            ->relationshipScope(
                $scope->tenantScope(ClassSection::query(), $actor),
                $actor,
                ClassSection::class
            )
            ->with(['standard', 'section'])
            ->find($classSection);

        if ($resolvedClassSection === null) {
            // Same 404-not-403 discipline as store() — see this class's
            // doc comment.
            abort(404);
        }

        $date = $request->query('date');
        $date = (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : now()->toDateString();

        $session = $request->query('session');
        $session = in_array($session, ['morning', 'afternoon'], true) ? $session : 'morning';

        $enrollments = $scope
            ->relationshipScope(StudentEnrollment::query(), $actor, StudentEnrollment::class)
            ->where('class_section_id', $resolvedClassSection->id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy(fn ($enrollment) => $enrollment->student->name ?? '')
            ->values();

        $records = $scope
            ->relationshipScope(AttendanceRecord::query(), $actor, AttendanceRecord::class)
            ->where('class_section_id', $resolvedClassSection->id)
            ->where('date', $date)
            ->where('session', $session)
            ->withCount('corrections')
            ->get()
            ->keyBy('student_id');

        return view('teacher.attendance.show', [
            'classSection' => $resolvedClassSection,
            'date' => $date,
            'session' => $session,
            'enrollments' => $enrollments,
            'records' => $records,
        ]);
    }

    public function store(Request $request, ScopeService $scope, AttendanceRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'class_section_id' => ['required', 'integer'],
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
            'date' => ['required', 'date'],
            'session' => ['required', Rule::in(['morning', 'afternoon'])],
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $classSection = $scope
            ->relationshipScope(
                $scope->tenantScope(ClassSection::query(), $actor),
                $actor,
                ClassSection::class
            )
            ->find($data['class_section_id']);

        if ($classSection === null) {
            // Same "resolves to not-found, not forbidden" discipline
            // DashboardController::showChild() documents — a teacher
            // guessing at another teacher's class_section_id gets no
            // signal the id is valid.
            abort(404);
        }

        try {
            $repository->mark(
                $actor->school_id,
                $classSection->academic_year_id,
                $classSection->id,
                $data['student_id'],
                $data['date'],
                $data['session'],
                $data['status'],
                $actor,
                $data['note'] ?? null,
                $data['reason'] ?? null
            );
        } catch (StudentNotEnrolledFailure $e) {
            return back()->withErrors(['student_id' => $e->getMessage()])->withInput();
        } catch (\InvalidArgumentException $e) {
            // The missing-reason-on-correction case from
            // AttendanceRepository::mark()'s own doc comment — surfaced
            // as a form error on the field the teacher needs to fill in,
            // not a 500, matching StudentNotEnrolledFailure's handling
            // immediately above.
            return back()->withErrors(['reason' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Attendance recorded.');
    }
}
