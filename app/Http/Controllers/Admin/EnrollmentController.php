<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Enrollment\DuplicateEnrollmentFailure;
use App\Exceptions\Enrollment\InvalidEnrollmentTargetFailure;
use App\Http\Controllers\Controller;
use App\Repositories\EnrollmentRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Design ref: 14-schoolos-implementation-plan.md §2
 *
 * First controller/route calling EnrollmentRepository::enroll() —
 * matches ClassSectionRepository's precedent (repository built and
 * tested well before any route called it) rather than being evidence
 * this route was always intended; see the handoff note this was raised
 * against for the "confirm, don't assume" caveat.
 *
 * No route parameter (this creates a row, it doesn't resolve one), so
 * it isn't in scope for row 8's single-record-by-ID lint. class_section_id
 * is scoped to the acting admin's own school via Rule::exists() the same
 * way ClassSectionController does — enroll()'s own cross-tenant guard
 * still runs behind this as the real enforcement, per its own doc
 * comment's "tenant scoping has already happened upstream" assumption.
 */
class EnrollmentController extends Controller
{
    public function store(Request $request, EnrollmentRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
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
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
            'roll_number' => ['required', 'string', 'max:255'],
        ]);

        try {
            $repository->enroll(
                $actor->school_id,
                $data['academic_year_id'],
                $data['student_id'],
                $data['class_section_id'],
                $data['roll_number'],
                $actor
            );
        } catch (InvalidEnrollmentTargetFailure $e) {
            return back()->withErrors(['student_id' => $e->getMessage()])->withInput();
        } catch (DuplicateEnrollmentFailure $e) {
            return back()->withErrors(['student_id' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Student enrolled.');
    }
}
