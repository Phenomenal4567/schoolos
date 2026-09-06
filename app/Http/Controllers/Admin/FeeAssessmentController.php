<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Fee\InvalidFeeAssessmentTargetFailure;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeAssessment;
use App\Models\FeeCategory;
use App\Models\User;
use App\Repositories\FeeAssessmentRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Design ref: 21-schoolos-finance-architecture.md §2,
 * 22-schoolos-finance-schema.md §2, §6.
 * Decision ref: 16-schoolos-decisions-register.md D11.
 *
 * school_admin/accountant's write and read surface for fee_assessments.
 * Given a real Blade UI per 21 §1's "School Finance / Admin Dashboard"
 * surface, every action here returns a View/RedirectResponse, not
 * JSON — this whole block is HTML-form-driven (a school_admin working a
 * real admin console), unlike the announcements.store/
 * scheme-of-work.store JSON-only convention used elsewhere for
 * endpoints a JS-only client calls directly. store()/adjust() have no
 * dedicated success JSON contract to preserve since nothing shipped
 * against this controller before this pass (unlike
 * ParentPortal\FeeController's own dual-response conversion, which had
 * to keep a pre-existing JSON caller working).
 *
 * index is tenant-scoped only — school_admin/accountant's authority
 * here is school-wide, per 22 §6's obligations table ("tenantScope
 * only" for the admin-facing row), not per-student. It also loads the
 * read-only option lists the "create assessment"/"grant scholarship"/
 * "add fee category" forms on that page need — same reasoning
 * ParentPortal\FeedbackController::index() gives for loading $children/
 * $teachers alongside the feedback list; none of those queries change
 * what store()/ScholarshipController::store()/FeeCategoryController::store()
 * themselves check. show resolves one row by route parameter, so it
 * carries 'scope.checked' at the route table and resolves
 * {feeAssessment} through ScopeService::tenantScope() here before
 * touching it, same discipline as Admin\CalendarEventController::update().
 */
class FeeAssessmentController extends Controller
{
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $assessments = $scope->tenantScope(FeeAssessment::query(), $actor)
            ->with(['student', 'feeCategory'])
            ->latest('id')
            ->get();

        $students = $scope->tenantScope(User::query(), $actor)
            ->whereHas('role', fn ($q) => $q->where('key', 'student'))
            ->orderBy('name')
            ->get();

        $academicYears = $scope->tenantScope(AcademicYear::query(), $actor)
            ->orderByDesc('id')
            ->get();

        $feeCategories = $scope->tenantScope(FeeCategory::query(), $actor)
            ->where('is_system_reserved', false)
            ->orderBy('label')
            ->get();

        return view('admin.fee-assessments.index', [
            'assessments' => $assessments,
            'students' => $students,
            'academicYears' => $academicYears,
            'feeCategories' => $feeCategories,
        ]);
    }

    public function show(Request $request, ScopeService $scope, int $feeAssessment): View
    {
        $actor = $request->user();

        $assessment = $scope->tenantScope(FeeAssessment::query(), $actor)
            ->with(['student', 'feeCategory', 'payments', 'discounts', 'rolledOverFrom'])
            ->find($feeAssessment);

        if ($assessment === null) {
            abort(404);
        }

        return view('admin.fee-assessments.show', [
            'assessment' => $assessment,
            'amountRemaining' => $assessment->amountRemaining(),
        ]);
    }

    public function store(Request $request, FeeAssessmentRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $actor->school_id),
            ],
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
            'fee_category_id' => [
                'required',
                'integer',
                Rule::exists('fee_categories', 'id')->where('school_id', $actor->school_id),
            ],
            'base_amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
            'discount_type' => ['nullable', 'string', 'in:individual,percentage,fixed'],
            'discount_value' => ['required_with:discount_type', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string'],
        ]);

        $discount = isset($data['discount_type']) ? [
            'type' => $data['discount_type'],
            'value' => (string) $data['discount_value'],
            'reason' => $data['discount_reason'] ?? null,
        ] : null;

        try {
            $repository->assess(
                $actor->school_id,
                $data['academic_year_id'],
                $data['student_id'],
                $data['fee_category_id'],
                (string) $data['base_amount'],
                $actor,
                $discount,
                $data['due_date'] ?? null,
            );
        } catch (InvalidFeeAssessmentTargetFailure $e) {
            return back()->withErrors(['student_id' => $e->getMessage()])->withInput();
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['fee_assessment' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.fee-assessments.index')->with('status', 'Fee assessment created.');
    }

    /**
     * D11's escape hatch — a discount/scholarship granted after
     * $feeAssessment already has payments against it. Resolves the
     * existing assessment through tenantScope() before adjusting it, so
     * carries 'scope.checked' at the route table.
     */
    public function adjust(Request $request, ScopeService $scope, FeeAssessmentRepository $repository, int $feeAssessment): RedirectResponse
    {
        $actor = $request->user();

        $existing = $scope->tenantScope(FeeAssessment::query(), $actor)->find($feeAssessment);

        if ($existing === null) {
            abort(404);
        }

        $data = $request->validate([
            'discount_type' => ['required', 'string', 'in:individual,percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string'],
        ]);

        $repository->adjust($existing->id, $actor, [
            'type' => $data['discount_type'],
            'value' => (string) $data['discount_value'],
            'reason' => $data['discount_reason'] ?? null,
        ]);

        return redirect()
            ->route('admin.fee-assessments.show', $existing)
            ->with('status', 'Adjustment recorded.');
    }
}
