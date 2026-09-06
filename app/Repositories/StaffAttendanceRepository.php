<?php

namespace App\Repositories;

use App\Exceptions\StaffAttendance\DuplicateStaffAttendanceFailure;
use App\Exceptions\StaffAttendance\NotAStaffMemberFailure;
use App\Exceptions\StaffAttendance\UnsupportedAttendanceMethodFailure;
use App\Models\StaffAttendanceRecord;
use App\Models\User;
use App\Services\QrTokenService;
use App\Services\ScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §7b, discovery §5.
 * Decision refs: 16-schoolos-decisions-register.md D12 (CONFIRMED,
 * unblocks the 'qr' method), D13 (PROPOSED, still blocks 'face' — see
 * UnsupportedAttendanceMethodFailure's doc comment).
 * Execution ref: 20-phase-6-8-execution-prompt.md §4.
 *
 * The one write path for staff_attendance_records, matching this
 * bundle's "one repository method owns a resource's creation" shape
 * (EnrollmentRepository::enroll(), CalendarEventRepository::create()).
 *
 * Only 'manual' and 'qr' are accepted right now:
 * - 'manual': an admin/self-attested check-in with no verification
 *   step — verification_result is null (there was nothing to verify).
 * - 'qr': the caller supplies a QrTokenService::issueAttendanceToken()
 *   token; checkIn() verifies it and requires the token's subject to
 *   match $staff (a staff member cannot check in using someone else's
 *   scanned token) — verification_result is 'verified' on success.
 *
 * 'selfie', 'face', 'fingerprint', 'gps' all throw
 * UnsupportedAttendanceMethodFailure — see that class's doc comment for
 * why each is currently rejected.
 */
class StaffAttendanceRepository
{
    /**
     * Role keys eligible for staff attendance — every seeded role except
     * 'student', 'parent', and 'super_admin' (a platform-level account
     * with no single school's attendance to check into). Matches
     * IdentifierService::generateStaffId()'s eligibility gate exactly —
     * both intentionally reject the same three roles, since a staff_id
     * and a staff-attendance row answer the same underlying question
     * ("is this person staff at this school").
     */
    public const STAFF_ROLE_KEYS = [
        'school_admin',
        'teacher',
        'accountant',
        'librarian',
        'receptionist',
        'staff',
    ];

    public function __construct(private readonly QrTokenService $qrTokens)
    {
    }

    /**
     * Records a check-in for $staff on $date (defaults to today) using
     * $method. $qrToken is required (and only meaningful) when
     * $method === 'qr'.
     *
     * Relies on the schema-level UNIQUE(user_id, date) constraint (13
     * §7b) — not a pre-check-then-insert — to reject a second check-in
     * for the same staff member/day, the same TOCTOU-safe shape
     * EnrollmentRepository::enroll() uses for UNIQUE(student_id,
     * academic_year_id).
     *
     * @throws NotAStaffMemberFailure if $staff's role isn't in
     *         self::STAFF_ROLE_KEYS.
     * @throws UnsupportedAttendanceMethodFailure if $method isn't
     *         'manual' or 'qr'.
     * @throws \InvalidArgumentException if $method === 'qr' and
     *         $qrToken is missing, invalid, expired, or was issued for
     *         a different user than $staff.
     * @throws DuplicateStaffAttendanceFailure if $staff already has a
     *         staff_attendance_records row for $date.
     */
    public function checkIn(
        User $staff,
        string $method,
        ?string $qrToken = null,
        ?string $date = null
    ): StaffAttendanceRecord {
        $this->assertIsStaff($staff);

        if (! in_array($method, ['manual', 'qr'], true)) {
            throw new UnsupportedAttendanceMethodFailure($method);
        }

        $verificationResult = null;

        if ($method === 'qr') {
            $verificationResult = $this->verifyQrCheckIn($staff, $qrToken);
        }

        $date ??= Carbon::now()->toDateString();

        try {
            return StaffAttendanceRecord::create([
                'school_id' => $staff->school_id,
                'user_id' => $staff->id,
                'date' => $date,
                'check_in' => Carbon::now(),
                'method' => $method,
                'verification_result' => $verificationResult,
                'status' => 'present',
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            // UNIQUE(user_id, date) rejected the insert — a genuine
            // second check-in attempt for the same day, not a race to
            // convert idempotently (unlike ClassTeacherAssignmentRepository
            // ::assign()'s "already true" case — a second check-in is a
            // business-rule violation the caller needs to see).
            throw new DuplicateStaffAttendanceFailure($staff, $date);
        }
    }

    /**
     * Staff attendance rows visible to $actor: their own rows only,
     * unless $actor is an admin (school_admin/super_admin), who sees
     * every row in their tenant. Delegates to
     * ScopeService::staffSelfScope() rather than relationshipScope() —
     * see StaffAttendanceRecord's own doc comment for why this table
     * has no branch in the shared relationshipScope() dispatch.
     */
    public function visibleTo(User $actor, ScopeService $scope): Builder
    {
        // tenantScope() already exempts super_admin internally (D5) —
        // no need to branch on role here before calling it.
        $query = $scope->tenantScope(StaffAttendanceRecord::query(), $actor);

        return $scope->staffSelfScope($query, $actor);
    }

    /**
     * @throws \InvalidArgumentException if $qrToken is missing, the
     *         token fails QrTokenService::verify(), or the token's
     *         subject isn't $staff (someone else's scanned code cannot
     *         be used to check $staff in).
     */
    private function verifyQrCheckIn(User $staff, ?string $qrToken): string
    {
        if ($qrToken === null || $qrToken === '') {
            throw new \InvalidArgumentException(
                'StaffAttendanceRepository::checkIn(): a qr token is required for the qr method.'
            );
        }

        // Let QrTokenService's own exceptions (InvalidQrTokenFailure,
        // ExpiredQrTokenFailure) propagate as-is — the controller layer
        // is what maps those to a user-facing "scan again" message,
        // this repository doesn't re-wrap them.
        $tokenSubject = $this->qrTokens->verify($qrToken);

        if ($tokenSubject->id !== $staff->id) {
            throw new \InvalidArgumentException(
                "StaffAttendanceRepository::checkIn(): qr token belongs to user #{$tokenSubject->id}, "
                . "not the checking-in user #{$staff->id}."
            );
        }

        return 'verified';
    }

    /**
     * @throws NotAStaffMemberFailure if $staff's role isn't in
     *         self::STAFF_ROLE_KEYS.
     */
    private function assertIsStaff(User $staff): void
    {
        if (! in_array($staff->role->key ?? null, self::STAFF_ROLE_KEYS, true)) {
            throw new NotAStaffMemberFailure($staff);
        }
    }
}
