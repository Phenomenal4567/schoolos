<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassSection;
use App\Models\LessonPlan;
use App\Models\Section;
use App\Models\Standard;
use App\Models\StudentParentLink;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 14-schoolos-implementation-plan.md §2, 15-academic-domain-map.md §2
 *
 * Closes the second gap flagged in the Phase 2 task doc: school_admin
 * had no dashboard at all — SessionController::dashboardPathFor() had no
 * branch for it, and every admin.* route was a bare POST/DELETE endpoint
 * with no matching GET view, so a real admin could not set up a school
 * without Tinker.
 *
 * A later session added the $subjects query — Phase 4's first slice
 * (15 §2), same tenantScope()-only shape as everything else here.
 *
 * index() is a read-only, id-less listing of the acting admin's own
 * school's academic years, standards, sections, subjects, and class
 * sections — the same shape as ParentPortal\DashboardController::index()
 * and TeacherPortal\DashboardController::index(), so it needs no
 * 'scope.checked' (no route parameter to resolve).
 *
 * Every query below is tenantScope()'d only — no relationshipScope()
 * call, matching ClassSectionController's own doc comment: the acting
 * role for this whole controller is 'school_admin', and
 * ScopeService::relationshipScope()'s own doc comment marks that role as
 * tenant-scope-only for Phase 1.
 *
 * A later session wired up the read side of every admin.* write path
 * that already had a controller/route but no dashboard view calling it
 * (EnrollmentController, ParentLinkController, LessonPlanController's
 * approve/reject, AnnouncementController — see each one's own doc
 * comment: "first controller/route calling ...Repository", predating any
 * view that could reach them). $teachers/$students/$parents are the
 * three role-filtered User lists the new class-section/enrollment/
 * parent-link forms populate their <select> options from — filtered by
 * User::role()->key the same way ScopeService keys its own role
 * dispatch, and tenantScope()'d like everything else here. $parentLinks
 * only includes 'status' = 'active' rows (an unlink()'d link is not
 * shown, matching StudentParentLink's own doc comment on what "active"
 * gates). $pendingLessonPlans only includes 'status' = 'submitted' rows
 * — approved/rejected plans have already been reviewed and don't belong
 * in an admin's pending queue.
 *
 * $school (SchoolOS Account Creation & Onboarding plan, §1): added so the
 * view can show a "finish setting up your school" banner when
 * $school->setup_status !== 'complete', linking into Admin\
 * SetupWizardController. Never gates this page — the banner is purely
 * informational, per that plan's own "the wizard never gates the
 * dashboard" decision.
 */
class DashboardController extends Controller
{
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $academicYears = $scope->tenantScope(AcademicYear::query(), $actor)
            ->orderByDesc('is_current')
            ->orderByDesc('label')
            ->get();

        $standards = $scope->tenantScope(Standard::query(), $actor)
            ->orderBy('name')
            ->get();

        $sections = $scope->tenantScope(Section::query(), $actor)
            ->orderBy('name')
            ->get();

        $subjects = $scope->tenantScope(Subject::query(), $actor)
            ->orderBy('name')
            ->get();

        $classSections = $scope->tenantScope(ClassSection::query(), $actor)
            ->with(['academicYear', 'standard', 'section', 'classTeacher'])
            ->get();

        $teachers = $scope->tenantScope(User::query(), $actor)
            ->whereHas('role', fn ($query) => $query->where('key', 'teacher'))
            ->orderBy('name')
            ->get();

        $students = $scope->tenantScope(User::query(), $actor)
            ->whereHas('role', fn ($query) => $query->where('key', 'student'))
            ->orderBy('name')
            ->get();

        $parents = $scope->tenantScope(User::query(), $actor)
            ->whereHas('role', fn ($query) => $query->where('key', 'parent'))
            ->orderBy('name')
            ->get();

        $parentLinks = $scope->tenantScope(StudentParentLink::query(), $actor)
            ->where('status', 'active')
            ->with(['parent', 'student'])
            ->get();

        // SchoolOS Account Creation & Onboarding plan, §4: self-service
        // requests (ParentLinkRepository::request()) awaiting
        // Admin\ParentLinkController::approve()/reject().
        $pendingParentLinkRequests = $scope->tenantScope(StudentParentLink::query(), $actor)
            ->where('status', 'pending')
            ->with(['parent', 'student'])
            ->get();

        $pendingLessonPlans = $scope->tenantScope(LessonPlan::query(), $actor)
            ->where('status', 'submitted')
            ->with(['teacher', 'subject', 'classSection.standard', 'classSection.section'])
            ->orderBy('created_at')
            ->get();

        return view('admin.dashboard', [
            'school' => $actor->school,
            'academicYears' => $academicYears,
            'standards' => $standards,
            'sections' => $sections,
            'subjects' => $subjects,
            'classSections' => $classSections,
            'teachers' => $teachers,
            'students' => $students,
            'parents' => $parents,
            'parentLinks' => $parentLinks,
            'pendingParentLinkRequests' => $pendingParentLinkRequests,
            'pendingLessonPlans' => $pendingLessonPlans,
        ]);
    }
}
