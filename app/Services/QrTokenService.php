<?php

namespace App\Services;

use App\Exceptions\QrToken\ExpiredQrTokenFailure;
use App\Exceptions\QrToken\InvalidQrTokenFailure;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Decision ref: 16-schoolos-decisions-register.md D12 (CONFIRMED).
 * Design ref: school_management_system_discovery_hierarchy.md §2.3
 * (ID-card QR), §5 (staff-attendance QR method).
 *
 * The single issue/verify service for every QR code this bundle prints
 * or scans — the student/staff ID card and the staff-attendance QR
 * check-in method both go through here, per D12's confirmed answer to
 * `20-phase-6-8-execution-prompt.md` §4's Decision Prompt A ("is it the
 * same payload/verification service"). One service instead of two ad
 * hoc payload formats, matching this bundle's established shape of one
 * component owning one concern (IdentifierService for the id columns,
 * this for QR tokens).
 *
 * A token is never a raw user id or student_id/staff_id printed as a
 * plain string — it's an authenticated-encrypted (Laravel's
 * `Crypt::encryptString()`, AES-256-CBC + HMAC under APP_KEY) blob of a
 * small JSON payload. This gets both confidentiality (a scanner can't
 * read who a card belongs to without going through this service) and
 * tamper-evidence (any modification fails decryption) for free, rather
 * than hand-rolling an HMAC-over-JSON scheme — no reason to reinvent
 * authenticated encryption Laravel already ships.
 *
 * Two distinct issuance modes, both verified by the same verify():
 * - ID-card tokens (issueIdCardToken): no expiry embedded. A card is a
 *   printed physical artifact — it cannot be silently reissued every
 *   few minutes the way a ScopeService check could invalidate a stale
 *   session, so its QR must remain scannable for the card's practical
 *   lifetime. Forgery resistance comes from the encryption, not an
 *   expiry window.
 * - Attendance tokens (issueAttendanceToken): a short TTL (default 5
 *   minutes, matching this track's other short-window figure) is
 *   embedded and enforced at verify() time — this is the "issued fresh
 *   for a scan/check-in event, not a static value printed once and
 *   valid forever" half of D12, for the check-in flow specifically
 *   (e.g. a kiosk or admin-scan screen that regenerates the code), not
 *   for the card itself.
 *
 * verify() does not check school-tenancy or role eligibility — that's
 * StaffAttendanceRepository::checkIn()'s and the ID-card controller's
 * job once they have a resolved User, matching this bundle's pattern of
 * services resolving identity/authenticity and repositories/controllers
 * enforcing business rules on top of it.
 */
class QrTokenService
{
    private const ATTENDANCE_DEFAULT_TTL_SECONDS = 300;

    /**
     * Issues a non-expiring token identifying $user, for printing on an
     * ID card. Idempotent in effect (verifying any token issued for the
     * same user resolves to the same user), but not idempotent in
     * output — each call produces different ciphertext (random IV),
     * which is fine: nothing persists or compares raw token strings,
     * only what verify() resolves them to.
     */
    public function issueIdCardToken(User $user): string
    {
        return $this->encode([
            'typ' => 'idcard',
            'sub' => $user->id,
            'sid' => $user->school_id,
            'iat' => now()->timestamp,
            'exp' => null,
        ]);
    }

    /**
     * Issues a token for the staff-attendance QR check-in flow, valid
     * for $ttlSeconds from issuance (default 5 minutes). Distinct from
     * issueIdCardToken() only in that an expiry is embedded and
     * enforced — same payload shape otherwise.
     */
    public function issueAttendanceToken(User $user, int $ttlSeconds = self::ATTENDANCE_DEFAULT_TTL_SECONDS): string
    {
        return $this->encode([
            'typ' => 'attendance',
            'sub' => $user->id,
            'sid' => $user->school_id,
            'iat' => now()->timestamp,
            'exp' => now()->addSeconds($ttlSeconds)->timestamp,
        ]);
    }

    /**
     * Decrypts and validates $token, returning the User it identifies.
     *
     * @throws InvalidQrTokenFailure if $token isn't a token this
     *         service issued (wrong APP_KEY, corrupted, malformed, or
     *         the subject user no longer exists).
     * @throws ExpiredQrTokenFailure if $token carried an expiry (an
     *         attendance token) and that expiry has passed. ID-card
     *         tokens (exp === null) never throw this.
     */
    public function verify(string $token): User
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new InvalidQrTokenFailure();
        }

        if (
            !is_array($payload)
            || !isset($payload['typ'], $payload['sub'], $payload['sid'], $payload['iat'])
            || !in_array($payload['typ'], ['idcard', 'attendance'], true)
        ) {
            throw new InvalidQrTokenFailure();
        }

        if (($payload['exp'] ?? null) !== null && now()->timestamp > $payload['exp']) {
            throw new ExpiredQrTokenFailure();
        }

        $user = User::find($payload['sub']);

        if ($user === null || $user->school_id !== $payload['sid']) {
            // school_id mismatch means the user was moved/reassigned
            // since the token was issued — treat exactly like "no
            // longer a valid subject" rather than silently trusting
            // the stale value embedded in the token.
            throw new InvalidQrTokenFailure();
        }

        return $user;
    }

    private function encode(array $payload): string
    {
        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
