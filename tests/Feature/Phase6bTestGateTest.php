<?php

namespace Tests\Feature;

use App\Exceptions\StaffAttendance\DuplicateStaffAttendanceFailure;
use App\Exceptions\StaffAttendance\NotAStaffMemberFailure;
use App\Exceptions\StaffAttendance\UnsupportedAttendanceMethodFailure;
use App\Models\StaffAttendanceRecord;
use App\Repositories\StaffAttendanceRepository;
use App\Services\QrTokenService;
use App\Services\ScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §4/§5.
 * Decision refs: 16-schoolos-decisions-register.md D12 (CONFIRMED —
 * everything this gate exercises depends only on D12), D13 (PROPOSED —
 * see the unsupported-method test's own doc comment for why 'face' is
 * asserted rejected, not accepted, in this gate).
 * Execution ref: 20-phase-6-8-execution-prompt.md §4.
 *
 * One test per item this track's build note lists: scoping, QR
 * verification, duplicate-checkin rejection, and unsupported-method
 * rejection — same "one test per gate row" shape as
 * Phase6aTestGateTest. FaceVerificationService is not exercised here at
 * all — it doesn't exist yet, and won't until D13 closes.
 */
class Phase6bTestGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * Scoping — a staff member sees only their own attendance rows
     * through StaffAttendanceRepository::visibleTo(), while a
     * school_admin at the same school sees every staff member's rows,
     * and never a different school's rows at all. Exercises
     * ScopeService::staffSelfScope() directly, per
     * StaffAttendanceRecord's own doc comment on why relationshipScope()
     * is never called for this model.
     */
    public function test_staff_attendance_is_scoped_to_self_unless_admin(): void
    {
        $scope = app(ScopeService::class);
        $repository = app(StaffAttendanceRepository::class);

        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();

        $teacherA1 = $this->makeRoleUser('teacher', $schoolA);
        $teacherA2 = $this->makeRoleUser('teacher', $schoolA);
        $adminA = $this->makeRoleUser('school_admin', $schoolA);
        $teacherB = $this->makeRoleUser('teacher', $schoolB);

        $recordA1 = $repository->checkIn($teacherA1, 'manual');
        $recordA2 = $repository->checkIn($teacherA2, 'manual');
        $repository->checkIn($teacherB, 'manual');

        // teacherA1 sees only their own row.
        $visibleToTeacher = $repository->visibleTo($teacherA1, $scope)->get();
        $this->assertCount(1, $visibleToTeacher);
        $this->assertSame($recordA1->id, $visibleToTeacher->first()->id);

        // adminA sees every row in their own tenant, never school B's.
        $visibleToAdmin = $repository->visibleTo($adminA, $scope)->get();
        $this->assertCount(2, $visibleToAdmin);
        $this->assertEqualsCanonicalizing(
            [$recordA1->id, $recordA2->id],
            $visibleToAdmin->pluck('id')->all()
        );
    }

    /**
     * QR verification — a token issued by QrTokenService for a given
     * staff member successfully checks that same staff member in via
     * StaffAttendanceRepository::checkIn(), and verification_result is
     * recorded as 'verified'. Also confirms a token issued for one user
     * cannot be used to check a *different* user in (identity is bound
     * to the token, not supplied separately by the caller).
     */
    public function test_qr_token_checks_in_its_own_subject_and_rejects_mismatched_use(): void
    {
        $qrTokens = app(QrTokenService::class);
        $repository = app(StaffAttendanceRepository::class);

        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);
        $otherTeacher = $this->makeRoleUser('teacher', $school);

        $token = $qrTokens->issueAttendanceToken($teacher);

        $record = $repository->checkIn($teacher, 'qr', $token);

        $this->assertSame('verified', $record->verification_result);
        $this->assertSame('qr', $record->method);
        $this->assertSame($teacher->id, $record->user_id);

        // The same token cannot check a different user in.
        $this->expectException(\InvalidArgumentException::class);
        $repository->checkIn($otherTeacher, 'qr', $token);
    }

    /**
     * Duplicate-checkin rejection — a second checkIn() for the same
     * staff member on the same day is rejected via the schema-level
     * UNIQUE(user_id, date) constraint, matching
     * DuplicateStaffAttendanceFailure's own TOCTOU-safe doc comment
     * (caught QueryException, not a pre-check-then-insert).
     */
    public function test_duplicate_check_in_for_the_same_day_is_rejected(): void
    {
        $repository = app(StaffAttendanceRepository::class);

        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $repository->checkIn($teacher, 'manual', date: '2026-08-31');

        $this->expectException(DuplicateStaffAttendanceFailure::class);
        $repository->checkIn($teacher, 'manual', date: '2026-08-31');
    }

    /**
     * Unsupported-method rejection — 'gps' and 'fingerprint' remain
     * rejected (no decision has ever covered either), and — the point
     * this test gate exists to lock in — 'face' is *also* still
     * rejected right now, because D13 is PROPOSED, not CONFIRMED.
     * FaceVerificationService does not exist; if a future change
     * accepts 'face' here without D13 first flipping to CONFIRMED in
     * the register, this test should fail and does so on purpose.
     */
    public function test_unsupported_methods_are_rejected_including_face_pending_d13(): void
    {
        $repository = app(StaffAttendanceRepository::class);

        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        foreach (['gps', 'fingerprint', 'face', 'selfie'] as $method) {
            try {
                $repository->checkIn($teacher, $method);
                $this->fail("Expected UnsupportedAttendanceMethodFailure for method '{$method}'.");
            } catch (UnsupportedAttendanceMethodFailure $e) {
                $this->assertStringContainsString($method, $e->getMessage());
            }
        }

        $this->assertSame(0, StaffAttendanceRecord::count());
    }

    /**
     * A role outside StaffAttendanceRepository::STAFF_ROLE_KEYS
     * (student/parent/super_admin) cannot check in at all, regardless
     * of method — mirrors EnrollmentRepository's own
     * InvalidEnrollmentTargetFailure-style role gate.
     */
    public function test_non_staff_roles_cannot_check_in(): void
    {
        $repository = app(StaffAttendanceRepository::class);

        $school = $this->makeSchool();
        $student = $this->makeRoleUser('student', $school);

        $this->expectException(NotAStaffMemberFailure::class);
        $repository->checkIn($student, 'manual');
    }
}
