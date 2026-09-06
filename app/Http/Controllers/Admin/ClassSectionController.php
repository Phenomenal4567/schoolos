<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ClassSection\InvalidClassTeacherFailure;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Repositories\ClassSectionRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 16-schoolos-decisions-register.md D3
 *
 * First controller/route calling ClassSectionRepository — before this,
 * assignClassTeacher() and create() were only exercised by their own
 * repository tests, the same "no controller calls it yet" gap
 * EnrollmentRepository and ParentLinkRepository are still in. Written as
 * a proposal to review, not a decision that admin routes were the right
 * next step to build — see the handoff note this was raised against.
 *
 * $schoolId is never read from the request; it is always the acting
 * admin's own school_id (Ground Rule 0). Every other identifier the
 * request supplies (academic_year_id, standard_id, section_id,
 * class_teacher_id) is validated with Rule::exists()->where('school_id',
 * ...) against that same value before it ever reaches the repository —
 * "tenant scoping has already happened upstream," the assumption
 * EnrollmentRepository::create()'s own cross-tenant guards are written
 * to expect from their caller, not something this controller can leave
 * for the repository to catch on its own.
 *
 * store() has no route parameter, so it falls outside row 8's
 * single-record-by-ID lint (Phase1TestGateTest) and carries no
 * 'scope.checked' middleware — there's no existing record to scope
 * against yet. assignTeacher() resolves an existing class_sections row
 * by ID, so it does carry 'scope.checked' and resolves that row through
 * ScopeService::tenantScope() before doing anything else — a
 * {classSection} belonging to another school 404s, identically to "does
 * not exist," the same F20-shaped guarantee DashboardController::
 * showChild() gives for student records. relationshipScope() is not
 * additionally called here: the acting role for this whole controller is
 * 'school_admin', and ScopeService::relationshipScope()'s own doc
 * comment marks that role as tenant-scope-only for Phase 1.
 */
class ClassSectionController extends Controller
{
    public function store(Request $request, ClassSectionRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $actor->school_id),
            ],
            'standard_id' => [
                'required',
                'integer',
                Rule::exists('standards', 'id')->where('school_id', $actor->school_id),
            ],
            'section_id' => [
                'required',
                'integer',
                Rule::exists('sections', 'id')->where('school_id', $actor->school_id),
            ],
            'class_teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
        ]);

        try {
            $repository->create(
                $actor->school_id,
                $data['academic_year_id'],
                $data['standard_id'],
                $data['section_id'],
                $data['class_teacher_id'],
                $actor
            );
        } catch (InvalidClassTeacherFailure $e) {
            // The role-scoped Rule::exists() above only confirms
            // class_teacher_id names a real user in this school — it
            // can't check role, since that's the repository's one gate
            // (D3). This is that gate rejecting, surfaced the same way
            // SessionController surfaces AuthFailure: the exception's own
            // message is already safe to show directly.
            return back()->withErrors(['class_teacher_id' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Class section created.');
    }

    public function assignTeacher(
        Request $request,
        ScopeService $scope,
        ClassSectionRepository $repository,
        int $classSection
    ): RedirectResponse {
        $actor = $request->user();

        $section = $scope->tenantScope(ClassSection::query(), $actor)->findOrFail($classSection);

        $data = $request->validate([
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
        ]);

        try {
            $repository->assignClassTeacher($section->id, $data['teacher_id'], $actor);
        } catch (InvalidClassTeacherFailure $e) {
            return back()->withErrors(['teacher_id' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Class teacher updated.');
    }
}
