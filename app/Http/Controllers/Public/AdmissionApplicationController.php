<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\FeeCategory;
use App\Models\School;
use App\Repositories\AdmissionApplicationRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §10, discovery
 * doc §12.
 *
 * Guest-facing public application form, scoped to a school via a
 * `?school=` query parameter carrying `schools.short_code`, not
 * `school_id` directly and not a URI route parameter — see this
 * controller's route registration in routes/web.php for why the query
 * parameter is a deliberate choice, not a stylistic one. The school row
 * is resolved from short_code server-side here regardless (Ground Rule
 * 0: never trust a client-supplied numeric school_id for an
 * unauthenticated write), the same posture as every other tenant-
 * scoping rule in this codebase, just without an authenticated actor to
 * scope from.
 *
 * No App\Http\Controllers route this controller registers has a URI
 * route parameter, so both actions are exempt from row 8's
 * 'scope.checked' lint by construction (Phase1TestGateTest's own
 * detection is a URI-shape check, not a semantic one — see the route
 * registration's doc comment).
 */
class AdmissionApplicationController extends Controller
{
    public function create(Request $request): View
    {
        $school = School::where('short_code', $request->query('school'))->firstOrFail();

        $feeCategories = FeeCategory::where('school_id', $school->id)->orderBy('label')->get();

        return view('public.admissions.create', [
            'school' => $school,
            'feeCategories' => $feeCategories,
        ]);
    }

    public function store(Request $request, AdmissionApplicationRepository $repository): RedirectResponse
    {
        $school = School::where('short_code', $request->input('school'))->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_no' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_relationship' => ['required', 'string', 'max:255'],
            'guardian_phone' => ['required', 'string', 'max:255'],
            'medical_info' => ['nullable', 'string'],
            'fee_category_acknowledgments' => ['required', 'array', 'min:1'],
            'fee_category_acknowledgments.*' => ['integer'],
        ]);

        try {
            $repository->submit(
                $school->id,
                [
                    'name' => $data['name'],
                    'date_of_birth' => $data['date_of_birth'],
                    'email' => $data['email'] ?? null,
                    'mobile_no' => $data['mobile_no'] ?? null,
                    'guardian_name' => $data['guardian_name'],
                    'guardian_relationship' => $data['guardian_relationship'],
                    'guardian_phone' => $data['guardian_phone'],
                    'medical_info' => $data['medical_info'] ?? null,
                ],
                $data['fee_category_acknowledgments']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['fee_category_acknowledgments' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('public.admissions.create', ['school' => $school->short_code])
            ->with('status', 'Application submitted. The school will be in touch.');
    }
}
