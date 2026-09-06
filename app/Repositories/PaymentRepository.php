<?php

namespace App\Repositories;

use App\Contracts\PaystackClient;
use App\Events\PaymentConfirmed;
use App\Exceptions\Payment\InvalidPaymentAmountFailure;
use App\Exceptions\Payment\PaystackVerificationFailure;
use App\Models\FeeAssessment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3,
 * 22-schoolos-finance-schema.md §4.
 * Decision ref: 16-schoolos-decisions-register.md D13.
 *
 * One ledger, one write path, for both Paystack and manual payments —
 * see Payment model's own doc comment for why a manual and an online
 * payment differ only in `processor`/`paystack_reference` vs
 * `receipt_upload_path`, never in table or code path. forAssessment() is
 * the one read path payments are ever queried through (21 §3), so
 * FeeController/PaymentController callers never build their own
 * ad hoc `Payment::where(...)` query.
 *
 * recordManualPayment() is admin-entered (per discovery §10.2 and
 * Payment's own doc comment on `recorded_by`) — it writes a
 * 'confirmed' row immediately, since an admin is attesting to a
 * payment that already happened (cash, bank transfer, a receipt
 * handed over in person), not initiating one. submitManualReceipt()
 * records a parent-uploaded receipt as a pending manual row; it does not
 * pay down the balance until an admin confirms the payment through the
 * manual-payment path. initiateOnlinePayment()/confirmOnlinePayment()
 * are the two-step Paystack flow: initiate()
 * records a 'pending' row when a parent starts a Paystack transaction
 * (the reference Paystack's own checkout returns to the client);
 * confirmOnlinePayment() is the only code path that flips it to
 * 'confirmed', and only after PaystackClient::verifyTransaction()
 * confirms it against Paystack's own API — never based on the
 * webhook payload's own claimed status (21 §3's trust-boundary rule,
 * the same discipline `12` §2 applies to authentication).
 */
class PaymentRepository
{
    public function __construct(private readonly PaystackClient $paystack)
    {
    }

    /**
     * @throws InvalidPaymentAmountFailure if $amount is not positive, or
     *         exceeds $feeAssessmentId's current amount_remaining.
     */
    public function recordManualPayment(
        int $schoolId,
        int $feeAssessmentId,
        string $amount,
        User $recordedBy,
        ?string $receiptUploadPath = null,
    ): Payment {
        $assessment = $this->assertAssessmentBelongsToSchool($feeAssessmentId, $schoolId);
        $this->assertValidAmount($assessment, $amount);

        $payment = DB::transaction(function () use ($schoolId, $assessment, $amount, $recordedBy, $receiptUploadPath) {
            $payment = Payment::create([
                'school_id' => $schoolId,
                'fee_assessment_id' => $assessment->id,
                'amount' => $amount,
                'processor' => 'manual',
                'paystack_reference' => null,
                'receipt_upload_path' => $receiptUploadPath,
                'status' => 'confirmed',
                'recorded_by' => $recordedBy->id,
            ]);

            $this->refreshAssessmentStatus($assessment);

            return $payment;
        });

        event(new PaymentConfirmed($payment));

        return $payment;
    }

    /**
     * Records the 'pending' row for a Paystack transaction a parent has
     * just started — no ledger effect yet (amountRemaining() ignores
     * non-'confirmed' rows), so this alone never pays down a balance.
     *
     * @throws InvalidPaymentAmountFailure if $amount is not positive, or
     *         exceeds $feeAssessmentId's current amount_remaining.
     * @throws \InvalidArgumentException if $paystackReference is already
     *         in use by another payment row.
     */
    public function initiateOnlinePayment(
        int $schoolId,
        int $feeAssessmentId,
        string $amount,
        string $paystackReference,
    ): Payment {
        $assessment = $this->assertAssessmentBelongsToSchool($feeAssessmentId, $schoolId);
        $this->assertValidAmount($assessment, $amount);

        if (Payment::where('paystack_reference', $paystackReference)->exists()) {
            throw new \InvalidArgumentException(
                "PaymentRepository::initiateOnlinePayment(): paystack_reference '{$paystackReference}' "
                . 'is already in use by another payment.'
            );
        }

        return Payment::create([
            'school_id' => $schoolId,
            'fee_assessment_id' => $assessment->id,
            'amount' => $amount,
            'processor' => 'paystack',
            'paystack_reference' => $paystackReference,
            'status' => 'pending',
            'recorded_by' => null,
        ]);
    }

    /**
     * Records a parent-submitted manual receipt for later admin review.
     * Pending manual receipts do not reduce amount_remaining.
     *
     * @throws InvalidPaymentAmountFailure if $amount is not positive, or
     *         exceeds $feeAssessmentId's current amount_remaining.
     */
    public function submitManualReceipt(
        int $schoolId,
        int $feeAssessmentId,
        string $amount,
        User $submittedBy,
        string $receiptUploadPath,
    ): Payment {
        $assessment = $this->assertAssessmentBelongsToSchool($feeAssessmentId, $schoolId);
        $this->assertValidAmount($assessment, $amount);

        return Payment::create([
            'school_id' => $schoolId,
            'fee_assessment_id' => $assessment->id,
            'amount' => $amount,
            'processor' => 'manual',
            'paystack_reference' => null,
            'receipt_upload_path' => $receiptUploadPath,
            'status' => 'pending',
            'recorded_by' => $submittedBy->id,
        ]);
    }

    /**
     * The only code path that moves a Paystack payment from 'pending' to
     * 'confirmed' — called from PaystackWebhookController, never from a
     * client-facing controller action directly (21 §3). Idempotent: a
     * webhook retry against an already-confirmed reference is a no-op,
     * not a double-count (Paystack's own retry policy means the same
     * event can arrive more than once).
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException if no
     *         pending/confirmed Paystack payment exists for $reference.
     * @throws PaystackVerificationFailure if Paystack's own API does not
     *         confirm this transaction as successful.
     */
    public function confirmOnlinePayment(string $paystackReference): Payment
    {
        $result = DB::transaction(function () use ($paystackReference) {
            $payment = Payment::where('paystack_reference', $paystackReference)
                ->where('processor', 'paystack')
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === 'confirmed') {
                return ['payment' => $payment, 'just_confirmed' => false];
            }

            if (! $this->paystack->verifyTransaction($paystackReference)) {
                throw new PaystackVerificationFailure(
                    $paystackReference,
                    'Paystack did not confirm this transaction as successful.'
                );
            }

            $payment->update(['status' => 'confirmed']);
            $this->refreshAssessmentStatus($payment->feeAssessment);

            return ['payment' => $payment->fresh(), 'just_confirmed' => true];
        });

        // Only the branch that actually flipped status fires the event —
        // a webhook retry against an already-confirmed reference must not
        // re-notify a parent of a payment they were already told about
        // (see PaymentConfirmed's own doc comment).
        if ($result['just_confirmed']) {
            event(new PaymentConfirmed($result['payment']));
        }

        return $result['payment'];
    }

    /**
     * Marks a Paystack payment 'failed' — called from
     * PaystackWebhookController on a charge-failure event. No ledger
     * effect (a 'failed' row already didn't count toward
     * amount_remaining), just a terminal status so it stops showing as
     * 'pending' indefinitely.
     */
    public function markOnlinePaymentFailed(string $paystackReference): Payment
    {
        $payment = Payment::where('paystack_reference', $paystackReference)
            ->where('processor', 'paystack')
            ->firstOrFail();

        $payment->update(['status' => 'failed']);

        return $payment->fresh();
    }

    /**
     * The one read path payments are ever queried through (21 §3) —
     * every controller that lists a fee_assessment's payment history
     * calls this rather than building its own query.
     */
    public function forAssessment(int $feeAssessmentId): Collection
    {
        return Payment::where('fee_assessment_id', $feeAssessmentId)
            ->orderBy('created_at')
            ->get();
    }

    private function assertValidAmount(FeeAssessment $assessment, string $amount): void
    {
        if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $assessment->amountRemaining(), 2) > 0) {
            throw new InvalidPaymentAmountFailure($assessment, $amount);
        }
    }

    private function assertAssessmentBelongsToSchool(int $feeAssessmentId, int $schoolId): FeeAssessment
    {
        $assessment = FeeAssessment::findOrFail($feeAssessmentId);

        if ($assessment->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "PaymentRepository: fee_assessment #{$feeAssessmentId} belongs to school "
                . "#{$assessment->school_id}, not the requested school #{$schoolId}."
            );
        }

        return $assessment;
    }

    /**
     * Recomputes and caches fee_assessments.status from the derived
     * amount_remaining — the one place this cached column is ever
     * written (FeeAssessment's own doc comment), kept in sync by the
     * same write path that records a payment, per 22 §2.
     */
    private function refreshAssessmentStatus(FeeAssessment $assessment): void
    {
        $assessment->refresh();
        $remaining = $assessment->amountRemaining();

        $status = match (true) {
            bccomp($remaining, '0', 2) <= 0 => 'paid',
            bccomp($remaining, (string) $assessment->amount_due, 2) < 0 => 'partially_paid',
            default => 'open',
        };

        $assessment->update(['status' => $status]);
    }
}
