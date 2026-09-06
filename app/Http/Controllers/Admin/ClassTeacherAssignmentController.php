<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ClassTeacherAssignment\InvalidAssignmentTargetFailure;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Repositories\ClassTeacherAssignmentRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 12 §3a / F18 (this table is what ScopeService::
 * teacherRelationshipScope() depends on)
 *
 * First controller/route calling ClassTeacherAssignmentRepository —
 * before this, assign() was only exercised by its own repository test.
 * Nested under /admin/class-sections/{classSection}/... rather than a
 * flat /admin/teacher-assignments, so the {classSection} route
 * parameter is what gets scope-checked and resolved once, and
 * academic_year_id is read from the resolved class section itself
 * (matching its academic year is a correctness requirement, not a
 * second piece of client input that could disagree with the first).
 *
 * store() carries 'scope.checked' and resolves {classSection} through
 * ScopeService::tenantScope() before anything else, for the same reason
 * ClassSectionController::assignTeacher() does — a {classSection}
 * belonging to another school 404s rather than reaching the repository
 * at all.
 */
class ClassTeacherAssignmentController extends Controller
{
    public function store(
        Request $request,
        ScopeService $scope,
        ClassTeacherAssignmentRepository $repository,
        int $classSection
    ): RedirectResponse {
        $actor = $request->user();

        $section = $scope->tenantScope(ClassSection::query(), $actor)->findOrFail($classSection);

        $data = $request->validate([
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where('school_id', $actor->school_id),
            ],
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
        ]);

        try {
            $repository->assign(
                $actor->school_id,
                $section->academic_year_id,
                $section->id,
                $data['subject_id'],
                $data['teacher_id'],
                $actor
            );
        } catch (InvalidAssignmentTargetFailure $e) {
            return back()->withErrors(['teacher_id' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Teacher assigned to subject.');
    }
}
