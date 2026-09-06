<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Admission\MissingDecisionReasonFailure;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\ClassSection;
use App\Repositories\AdmissionApplicationRepository;
use App\Repositories\EnrollmentRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §10, discovery
 * doc §12.
 * Decision ref: 16-schoolos-decisions-register.md D16.
 *
 * school_admin's read + decide surface for admission applications.
 * index is an id-less tenant-scoped listing, same shape as
 * Admin\PromotionController::index() (school_admin is tenant-scope-only
 * by default per ScopeService's own doc comment). show and every
 * decision action resolve a single row by route parameter and carry
 * 'scope.checked', per row 8's route-table lint — unlike
 * Admin\PromotionController::runAutomatic()/override(), which resolve
 * their targets from body parameters and are documented as exempt
 * instead, every action here already has a natural single-record route
 * parameter (the application being decided), so there's no reason not
 * to use it and pick up the lint's coverage directly.
 */
class AdmissionApplicationController extends Controller
{
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $applications = $scope->tenantScope(AdmissionApplication::query(), $actor)
            ->with(['decidedBy', 'resultingUser', 'resultingStudentEnrollment'])
            ->latest('submitted_at')
            ->get();

        return view('admin.admission-applications.index', ['applications' => $applications]);
    }

    public function show(Request $request, ScopeService $scope, int $admissionApplication): View
    {
        $actor = $request->user();

        $resolved = $scope->tenantScope(AdmissionApplication::query(), $actor)
            ->with(['decidedBy', 'resultingUser', 'resultingStudentEnrollment'])
            ->find($admissionApplication);

        if ($resolved === null) {
            abort(404);
        }

        // View-only data for the accept-form's placement dropdown below —
        // same reasoning TeacherPortal\ExamController::index() gives for
        // its own "new exam" form's classSections/subjects.
        $academicYears = $scope->tenantScope(AcademicYear::query(), $actor)->orderByDesc('id')->get();
        $classSections = $scope->tenantScope(ClassSection::query(), $actor)
            ->with(['standard', 'section'])
            ->get();

        return view('admin.admission-applications.show', [
            'application' => $resolved,
            'academicYears' => $academicYears,
            'classSections' => $classSections,
        ]);
    }

    public function markUnderReview(
        Request $request,
        ScopeService $scope,
        int $admissionApplication,
        AdmissionApplicationRepository $repository
    ): RedirectResponse {
        $actor = $request->user();

        $application = $scope->tenantScope(AdmissionApplication::query(), $actor)->find($admissionApplication);

        if ($application === null) {
            abort(404);
        }

        try {
            $repository->markUnderReview($actor->school_id, $application, $actor);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['admission' => $e->getMessage()]);
        }

        return back()->with('status', 'Application marked under review.');
    }

    public function reject(
        Request $request,
        ScopeService $scope,
        int $admissionApplication,
        AdmissionApplicationRepository $repository
    ): RedirectResponse {
        return $this->decideTerminal($request, $scope, $admissionApplication, $repository, 'reject');
    }

    public function withdraw(
        Request $request,
        ScopeService $scope,
        int $admissionApplication,
        AdmissionApplicationRepository $repository
    ): RedirectResponse {
        return $this->decideTerminal($request, $scope, $admissionApplication, $repository, 'withdraw');
    }

    public function accept(
        Request $request,
        ScopeService $scope,
        int $admissionApplication,
        AdmissionApplicationRepository $repository,
        EnrollmentRepository $enrollmentRepository
    ): RedirectResponse {
        $actor = $request->user();

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'class_section_id' => ['required', 'integer'],
            'roll_number' => ['required', 'string', 'max:255'],
        ]);

        $application = $scope->tenantScope(AdmissionApplication::query(), $actor)->find($admissionApplication);

        if ($application === null) {
            abort(404);
        }

        try {
            $repository->accept(
                $actor->school_id,
                $application,
                $data['academic_year_id'],
                $data['class_section_id'],
                $data['roll_number'],
                $actor,
                $enrollmentRepository
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['admission' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Application accepted and student enrolled.');
    }

    private function decideTerminal(
        Request $request,
        ScopeService $scope,
        int $admissionApplication,
        AdmissionApplicationRepository $repository,
        string $action
    ): RedirectResponse {
        $actor = $request->user();

        $data = $request->validate([
            'decision_reason' => ['required', 'string'],
        ]);

        $application = $scope->tenantScope(AdmissionApplication::query(), $actor)->find($admissionApplication);

        if ($application === null) {
            abort(404);
        }

        try {
            if ($action === 'reject') {
                $repository->reject($actor->school_id, $application, $data['decision_reason'], $actor);
            } else {
                $repository->withdraw($actor->school_id, $application, $data['decision_reason'], $actor);
            }
        } catch (MissingDecisionReasonFailure|\InvalidArgumentException $e) {
            return back()->withErrors(['admission' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Application ' . ($action === 'reject' ? 'rejected' : 'withdrawn') . '.');
    }
}
