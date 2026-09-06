<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Enrollment\DuplicateEnrollmentFailure;
use App\Exceptions\Enrollment\InvalidEnrollmentTargetFailure;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\InvitationRepository;
use App\Repositories\StudentRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §5 (Student
 * onboarding)
 *
 * store() closes the one genuine gap: before this, the only way a new
 * student User row ever got created was Public\AdmissionApplicationController's
 * public application form — there was no admin-direct "register a
 * walk-in student" action at all (Admin\EnrollmentController::store()
 * only enrolls an *already-existing* student user, per its own
 * Rule::exists('users', 'id') validation). invite() is the "enable
 * portal login" action the plan's STUDENT RECORD vs. STUDENT LOGIN
 * ACCOUNT distinction calls for — see StudentRepository's own doc
 * comment for why no schema change was needed to express that
 * distinction.
 */
class StudentController extends Controller
{
    public function store(Request $request, StudentRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'mobile_no' => ['nullable', 'string', 'max:255', 'unique:users,mobile_no'],
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $actor->school_id),
            ],
            'class_section_id' => [
                'required',
                'integer',
                Rule::exists('class_sections', 'id')->where('school_id', $actor->school_id),
            ],
            'roll_number' => ['required', 'string', 'max:255'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
        ]);

        try {
            $result = $repository->register(
                $actor->school_id,
                $data['academic_year_id'],
                $data['class_section_id'],
                $data['name'],
                $data['email'] ?? null,
                $data['mobile_no'] ?? null,
                $data['roll_number'],
                $actor,
                $data['parent_ids'] ?? [],
            );
        } catch (InvalidEnrollmentTargetFailure|DuplicateEnrollmentFailure $e) {
            return back()->withErrors(['roll_number' => $e->getMessage()])->withInput();
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['class_section_id' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "Student {$result['student']->name} registered and enrolled.");
    }

    /**
     * Enables portal login for an already-registered student — the only
     * new capability the plan's record-vs-login-account distinction
     * needs (see StudentRepository's own doc comment). Resolves
     * $student through ScopeService::tenantScope() first, same
     * 404-not-403 discipline as every other single-record admin action.
     */
    public function invite(Request $request, ScopeService $scope, InvitationRepository $invitations, int $student): RedirectResponse
    {
        $actor = $request->user();

        $resolved = $scope->tenantScope(User::query(), $actor)
            ->whereHas('role', fn ($query) => $query->where('key', 'student'))
            ->find($student);

        if ($resolved === null) {
            abort(404);
        }

        $invitations->issue($resolved, $actor);

        return back()->with('status', 'Student invitation sent.');
    }
}
