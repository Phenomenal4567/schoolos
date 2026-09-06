<?php

namespace App\Exceptions\StaffAttendance;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §7b. Decision ref:
 * 16-schoolos-decisions-register.md D13 (PROPOSED, not confirmed — see
 * that entry's status).
 *
 * Thrown for 'gps', 'fingerprint', 'selfie', and 'face'. 'gps' and
 * 'fingerprint' remain reserved-but-unimplemented enum values on
 * staff_attendance_records.method (schema doc's own framing) with no
 * decision recorded for either at all. 'face' is reserved-but-unimplemented
 * for a different reason: D13 — the decision that would unblock it — is
 * PROPOSED, not CONFIRMED (no product/legal sign-off has happened), so
 * FaceVerificationService has deliberately not been built and 'face' is
 * rejected the same as any other unimplemented method until D13 closes.
 * 'selfie' was named as shippable in this register's older MVP-scope
 * note, but no camera-verification service exists in this codebase yet
 * either (nothing in this build pass implements it) — rejected here
 * rather than accepted on the strength of that older note alone, since
 * accepting it would mean inventing an image-capture/persistence shape
 * for it without the same scrutiny D13 just went through for 'face'.
 *
 * Only 'manual' and 'qr' are currently accepted by
 * StaffAttendanceRepository::checkIn() — see that class's doc comment.
 */
class UnsupportedAttendanceMethodFailure extends \Exception
{
    public function __construct(public readonly string $method)
    {
        parent::__construct(
            "Staff attendance method '{$method}' is reserved but not yet supported — "
            . 'no retention/access decision has been recorded for it (see 16-schoolos-decisions-register.md).'
        );
    }
}
