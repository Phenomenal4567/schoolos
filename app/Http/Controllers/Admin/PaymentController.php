<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Payment\InvalidPaymentAmountFailure;
use App\Http\Controllers\Controller;
use App\Models\FeeAssessment;
use App\Repositories\PaymentRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3,
 * 22-schoolos-finance-schema.md §4, §6.
 * Decision ref: 16-schoolos-decisions-register.md D13.
 *
 * school_admin/accountant's manual-payment recording surface (discovery
 * §10.2 — cash, bank transfer, or a receipt handed over in person). The
 * "Record payment" form lives on admin.fee-assessments.show, so a
 * successful submit redirects back there (same View/RedirectResponse
 * convention as the rest of this block). store() resolves
 * {feeAssessment} through ScopeService::tenantScope() before writing
 * against it, so it carries 'scope.checked' at the route table, same
 * discipline as Admin\FeeAssessmentController::show(). No online-payment
 * initiation here — that's ParentPortal\PaymentController's surface; an
 * admin never initiates a Paystack transaction on a parent's behalf.
 */
class PaymentController extends Controller
{
    public function store(
        Request $request,
        ScopeService $scope,
        PaymentRepository $repository,
        int $feeAssessment
    ): RedirectResponse {
        $actor = $request->user();

        $assessment = $scope->tenantScope(FeeAssessment::query(), $actor)->find($feeAssessment);

        if ($assessment === null) {
            abort(404);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'receipt_upload_path' => ['nullable', 'string'],
        ]);

        try {
            $repository->recordManualPayment(
                $actor->school_id,
                $assessment->id,
                (string) $data['amount'],
                $actor,
                $data['receipt_upload_path'] ?? null,
            );
        } catch (InvalidPaymentAmountFailure $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.fee-assessments.show', $assessment)
            ->with('status', 'Payment recorded.');
    }
}
