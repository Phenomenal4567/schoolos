<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Fee\InvalidScholarshipTargetFailure;
use App\Http\Controllers\Controller;
use App\Repositories\ScholarshipRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Design ref: 22-schoolos-finance-schema.md §3 (discovery §10.5).
 *
 * school_admin/accountant's authoring surface for scholarships —
 * store-only, redirect-back, same View/RedirectResponse convention as
 * the rest of this block (Admin\FeeAssessmentController's own doc
 * comment). The "Grant scholarship" form lives on
 * admin.fee-assessments.index, same reasoning as
 * Admin\FeeCategoryController's own doc comment. student_id and
 * fee_category_id are both constrained to the acting admin's own school
 * via Rule::exists() the same way EnrollmentController does for its own
 * tenant-scoped foreign keys — ScholarshipRepository::grant()'s own
 * cross-tenant guard on fee_category_id still runs behind this as the
 * real enforcement, matching that repository's own doc comment. No
 * route parameter, so exempt from row 8's lint.
 */
class ScholarshipController extends Controller
{
    public function store(Request $request, ScholarshipRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $actor->school_id),
            ],
            'type' => ['required', 'string', 'in:full,partial,specific_exemption'],
            'value' => ['nullable', 'numeric'],
            'fee_category_id' => [
                'nullable',
                'integer',
                Rule::exists('fee_categories', 'id')->where('school_id', $actor->school_id),
            ],
            'reason' => ['nullable', 'string'],
        ]);

        try {
            $repository->grant(
                $actor->school_id,
                $data['student_id'],
                $data['academic_year_id'],
                $data['type'],
                isset($data['value']) ? (string) $data['value'] : null,
                $data['fee_category_id'] ?? null,
                $actor,
                $data['reason'] ?? null,
            );
        } catch (InvalidScholarshipTargetFailure $e) {
            return back()->withErrors(['student_id' => $e->getMessage()])->withInput();
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['scholarship' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.fee-assessments.index')->with('status', 'Scholarship granted.');
    }
}
