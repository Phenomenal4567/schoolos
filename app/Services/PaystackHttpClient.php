<?php

namespace App\Services;

use App\Contracts\PaystackClient;
use Illuminate\Support\Facades\Http;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3, D13.
 *
 * Real implementation of PaystackClient, calling Paystack's transaction
 * verify endpoint (GET /transaction/verify/{reference}) with the secret
 * key from config('services.paystack.secret_key'). A non-200 response,
 * a transport failure, or a response whose 'data.status' is not
 * 'success' all resolve to false — this method's only job is to answer
 * "did Paystack confirm this," not to interpret failure reasons for the
 * caller (PaymentRepository raises its own typed
 * PaystackVerificationFailure with more context when this returns
 * false).
 */
class PaystackHttpClient implements PaystackClient
{
    public function verifyTransaction(string $reference): bool
    {
        $secretKey = config('services.paystack.secret_key');

        if (empty($secretKey)) {
            return false;
        }

        try {
            $response = Http::withToken($secretKey)
                ->get("https://api.paystack.co/transaction/verify/{$reference}");
        } catch (\Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        return ($response->json('data.status') ?? null) === 'success';
    }
}
