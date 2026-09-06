<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\FeeAssessment;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 21-schoolos-finance-architecture.md §5,
 * 22-schoolos-finance-schema.md §6 (discovery §11).
 *
 * Read-only fee view for a parent's linked children. Renders Blade views
 * (this track's "Fee Portal" surface, 21 §1) but keeps a JSON contract
 * for a fetch()/API caller too — same wantsJson()-branch shape
 * ParentPortal\PaymentController::store() (this track's own write path)
 * uses; FeedbackController::index()/show() don't actually branch on
 * wantsJson() (they're View-only), so the return type here has to
 * cover both a View and a Response, not just Response — see below.
 *
 * ResolvesScopedAcademicResource works unmodified here — FeeAssessment
 * carries student_id directly (its own doc comment), so
 * ScopeService::relationshipScope()'s existing student_id-column branch
 * applies with no new ScopeService code, exactly per this track's
 * kickoff prompt (20 §5). show() resolves one row by route parameter, so
 * it carries 'scope.checked' at the route table, and 404-not-403 on
 * another family's assessment — the read-only half of 21 §5's "a parent
 * must never view or pay against another student's fee assessment"
 * rule. No store() — parents never author assessments.
 */
class FeeController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View|Response
    {
        $assessments = $this->scopedIndex($request, $scope, FeeAssessment::class, ['feeCategory', 'student']);

        $data = $assessments->map(fn (FeeAssessment $assessment) => [
            'assessment' => $assessment,
            'amount_remaining' => $assessment->amountRemaining(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['data' => $data]);
        }

        return view('parent.fees.index', ['fees' => $data]);
    }

    public function show(Request $request, ScopeService $scope, int $feeAssessment): View|Response
    {
        $resolved = $this->scopedFind($request, $scope, FeeAssessment::class, $feeAssessment, ['feeCategory', 'student', 'payments', 'discounts']);

        if ($resolved === null) {
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $resolved, 'amount_remaining' => $resolved->amountRemaining()]);
        }

        return view('parent.fees.show', [
            'assessment' => $resolved,
            'amountRemaining' => $resolved->amountRemaining(),
        ]);
    }
}
