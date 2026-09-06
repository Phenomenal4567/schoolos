<?php

namespace App\Http\Controllers;

use App\Exceptions\Payment\PaystackVerificationFailure;
use App\Repositories\PaymentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3, D13.
 *
 * Public — no 'auth' middleware, no role gate: Paystack calls this
 * directly, server-to-server, with no SchoolOS session. Deliberately
 * lives at the top-level App\Http\Controllers namespace, not under
 * Admin/ParentPortal/StudentPortal, since it belongs to none of those
 * portals' role-gated route groups (see routes/web.php's own comment on
 * this route for the matching CSRF exemption every other public route
 * in this app doesn't need).
 *
 * Never trusts the payload's own claimed event/status — the only thing
 * read from the request body is the transaction reference, which is
 * then re-verified against Paystack's own API via
 * PaymentRepository::confirmOnlinePayment() (21 §3's trust-boundary
 * rule, D13). A 'charge.success' event confirms; anything else (a
 * failure event, or a reference PaystackClient can't verify) marks the
 * payment 'failed' rather than leaving it 'pending' forever. Always
 * returns 200 on a structurally valid, if functionally negative, event
 * — a 4xx/5xx here would make Paystack retry the same webhook
 * indefinitely for an outcome that isn't going to change.
 */
class PaystackWebhookController extends Controller
{
    public function handle(Request $request, PaymentRepository $repository): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string'],
            'data.reference' => ['required', 'string'],
        ]);

        $reference = $data['data']['reference'] ?? $request->input('data.reference');
        $event = $data['event'];

        try {
            if ($event === 'charge.success') {
                $repository->confirmOnlinePayment($reference);
            } else {
                $repository->markOnlinePaymentFailed($reference);
            }
        } catch (PaystackVerificationFailure $e) {
            Log::warning($e->getMessage());
            $repository->markOnlinePaymentFailed($reference);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // No matching pending Paystack payment for this reference —
            // nothing this endpoint can act on; acknowledge anyway so
            // Paystack doesn't retry an event about a reference we never
            // initiated (or already resolved through a prior delivery).
        }

        return response()->json(['received' => true]);
    }
}
