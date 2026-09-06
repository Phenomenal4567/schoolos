<?php

namespace App\Http\Controllers\ParentPortal;

use App\Exceptions\Payment\InvalidPaymentAmountFailure;
use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\FeeAssessment;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 21-schoolos-finance-architecture.md sections 3 and 5.
 * Decision ref: 16-schoolos-decisions-register.md D13.
 *
 * The one write path this track's kickoff prompt (20 section 5) singles out as
 * strictly worse than a read-only gap: a scope hole here would let a
 * parent pay down, or upload a fraudulent receipt against, another
 * family's balance. store() resolves {feeAssessment} through
 * ResolvesScopedAcademicResource::scopedFind() before ever calling
 * PaymentRepository, so a wrong-family assessment 404s before a payment
 * row can be created against it at all.
 *
 * Paystack submissions create pending online rows; confirmation remains
 * PaystackWebhookController's server-side responsibility. Receipt
 * uploads create pending manual rows with a stored file, but do not pay
 * down the balance until an admin verifies and records the payment.
 */
class PaymentController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function store(
        Request $request,
        ScopeService $scope,
        PaymentRepository $repository,
        int $feeAssessment
    ): Response {
        $resolved = $this->scopedFind($request, $scope, FeeAssessment::class, $feeAssessment);

        if ($resolved === null) {
            abort(404);
        }

        $mode = $request->input('payment_method', 'paystack');

        $payment = $mode === 'receipt_upload'
            ? $this->submitReceiptUpload($request, $repository, $resolved)
            : $this->initiatePaystackPayment($request, $repository, $resolved);

        if ($payment instanceof Response) {
            return $payment;
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $payment], 201);
        }

        $message = $mode === 'receipt_upload'
            ? 'Receipt uploaded. The school will confirm it after review.'
            : 'Payment started. It will confirm once Paystack verifies it.';

        return redirect()
            ->route('parent.fees.show', $resolved)
            ->with('status', $message);
    }

    private function initiatePaystackPayment(Request $request, PaymentRepository $repository, FeeAssessment $assessment): Payment|Response
    {
        try {
            $data = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'paystack_reference' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }

            throw $e;
        }

        try {
            return $repository->initiateOnlinePayment(
                $assessment->school_id,
                $assessment->id,
                (string) $data['amount'],
                $data['paystack_reference'],
            );
        } catch (InvalidPaymentAmountFailure $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['amount' => $e->getMessage()]], 422);
            }

            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['paystack_reference' => $e->getMessage()]], 422);
            }

            return back()->withErrors(['paystack_reference' => $e->getMessage()])->withInput();
        }
    }

    private function submitReceiptUpload(Request $request, PaymentRepository $repository, FeeAssessment $assessment): Payment|Response
    {
        try {
            $data = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            ]);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }

            throw $e;
        }

        $path = $request->file('receipt')->store('payment-receipts', 'public');

        try {
            return $repository->submitManualReceipt(
                $assessment->school_id,
                $assessment->id,
                (string) $data['amount'],
                $request->user(),
                $path,
            );
        } catch (InvalidPaymentAmountFailure $e) {
            Storage::disk('public')->delete($path);

            if ($request->wantsJson()) {
                return response()->json(['errors' => ['amount' => $e->getMessage()]], 422);
            }

            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }
    }
}
