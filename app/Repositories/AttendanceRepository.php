<?php

namespace App\Repositories;

use App\Events\AttendanceCorrected;
use App\Events\AttendanceMarked;
use App\Exceptions\Attendance\StudentNotEnrolledFailure;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\ClassSection;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §5
 * Decision ref: 14-schoolos-implementation-plan.md §3
 *
 * The write path that creates/corrects an attendance_records row.
 * Mirrors EnrollmentRepository's shape (tenant/year consistency guards
 * on $classSectionId, checked before the transaction opens) with two
 * additions: unlike EnrollmentRepository and ClassTeacherAssignmentRepository
 * (both explicitly non-audit-logging by design, per their own doc
 * comments), this one writes to audit_logs for the general activity
 * feed *and* to the dedicated attendance_corrections table (13 §5) when
 * the write is a correction rather than a first mark — see that table's
 * migration/model doc comments for why both exist rather than one.
 *
 * Also dispatches AttendanceMarked/AttendanceCorrected — but only after
 * DB::transaction() below has returned, i.e. only after commit. This is
 * deliberately outside the transaction closure, not inside it: 09-
 * attendance-map.md §5 flags GegoK12's inline parent-notification fan-out
 * (inside the same loop/transaction as the write) as coupling worth
 * avoiding, and dispatching before commit would let a listener act on a
 * write that could still roll back. Nothing subscribes to either event
 * yet — see each event class's own doc comment — this repository's job
 * ends at "the write committed and the fact was announced," not at
 * "a parent was notified."
 */
class AttendanceRepository
{
    /**
     * The one write path for attendance_records. Upserts against the
     * schema-level UNIQUE(student_id, date, session) constraint — a
     * second call for the same student/date/session updates the existing
     * row (a correction) rather than being rejected, unlike
     * EnrollmentRepository::enroll()'s duplicate-is-an-error stance:
     * attendance for a given session is expected to be correctable by
     * the recording teacher, not a one-shot fact.
     *
     * $schoolId and $academicYearId are taken from the caller's own
     * scoped context, never client input directly, per Ground Rule 0.
     * $classSectionId is checked against both — a caller-side consistency
     * bug, not end-user input validation, matching
     * EnrollmentRepository::enroll()'s identical guards.
     *
     * $reason is required (and only meaningful) when this call corrects
     * an existing row — attendance_corrections.reason is NOT NULL at the
     * schema level (13 §5), so a correction with no stated reason is
     * rejected here rather than silently coercing to an empty string.
     * $reason is ignored on a first mark (no prior state to explain a
     * change from).
     *
     * @throws StudentNotEnrolledFailure if $studentId has no active
     *         student_enrollments row for $classSectionId.
     * @throws \InvalidArgumentException if $classSectionId doesn't belong
     *         to $schoolId or $academicYearId, or if this call corrects
     *         an existing row without a $reason.
     */
    public function mark(
        int $schoolId,
        int $academicYearId,
        int $classSectionId,
        int $studentId,
        string $date,
        string $session,
        string $status,
        User $actor,
        ?string $note = null,
        ?string $reason = null
    ): AttendanceRecord {
        $classSection = ClassSection::findOrFail($classSectionId);

        if ($classSection->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "AttendanceRepository::mark(): class_section #{$classSectionId} belongs to "
                . "school #{$classSection->school_id}, not the requested school #{$schoolId}."
            );
        }

        if ($classSection->academic_year_id !== $academicYearId) {
            throw new \InvalidArgumentException(
                "AttendanceRepository::mark(): class_section #{$classSectionId} belongs to "
                . "academic year #{$classSection->academic_year_id}, not the requested academic "
                . "year #{$academicYearId}."
            );
        }

        $enrollment = StudentEnrollment::where('student_id', $studentId)
            ->where('class_section_id', $classSectionId)
            ->where('status', 'active')
            ->first();

        if ($enrollment === null) {
            $student = User::findOrFail($studentId);

            throw new StudentNotEnrolledFailure($student, $classSectionId);
        }

        // The transaction closure returns a small result shape, not just
        // the AttendanceRecord — dispatching AttendanceMarked vs.
        // AttendanceCorrected (and, for the latter, the previous/new
        // status + reason) needs to know which branch actually ran and
        // with what correction context, and that decision is only made
        // inside the closure. Re-deriving it afterwards (e.g. "was a
        // matching attendance_corrections row just created?") would mean
        // a second query for something already known at write time.
        $result = DB::transaction(function () use (
            $schoolId,
            $academicYearId,
            $classSectionId,
            $studentId,
            $date,
            $session,
            $status,
            $actor,
            $note,
            $reason
        ) {
            // Locks the row for the duration of the transaction so a
            // second concurrent call for the same (student_id, date,
            // session) blocks here rather than racing past this check —
            // the schema-level unique constraint is the structural
            // backstop if two transactions somehow still interleave, but
            // this lock is what makes the common case a clean serialized
            // update instead of a constraint-violation retry.
            $existing = AttendanceRecord::where('student_id', $studentId)
                ->where('date', $date)
                ->where('session', $session)
                ->lockForUpdate()
                ->first();

            $afterState = [
                'class_section_id' => $classSectionId,
                'status' => $status,
                'note' => $note,
            ];

            if ($existing !== null) {
                if ($reason === null || trim($reason) === '') {
                    throw new \InvalidArgumentException(
                        'AttendanceRepository::mark(): correcting an existing attendance_records row '
                        . "(#{$existing->id}) requires a reason — attendance_corrections.reason is "
                        . 'NOT NULL at the schema level.'
                    );
                }

                $beforeState = [
                    'class_section_id' => $existing->class_section_id,
                    'status' => $existing->status,
                    'note' => $existing->note,
                ];
                $previousStatus = $existing->status;

                $existing->fill([
                    'academic_year_id' => $academicYearId,
                    'class_section_id' => $classSectionId,
                    'recorded_by' => $actor->id,
                    'status' => $status,
                    'note' => $note,
                    'recorded_at' => now(),
                ]);
                $existing->save();

                AttendanceCorrection::create([
                    'attendance_record_id' => $existing->id,
                    'previous_status' => $previousStatus,
                    'new_status' => $status,
                    'corrected_by' => $actor->id,
                    'corrected_at' => now(),
                    'reason' => $reason,
                ]);

                AuditLog::create([
                    'school_id' => $schoolId,
                    'actor_id' => $actor->id,
                    'action' => 'attendance.corrected',
                    'entity_type' => 'AttendanceRecord',
                    'entity_id' => $existing->id,
                    'before_state' => $beforeState,
                    'after_state' => $afterState,
                ]);

                return [
                    'record' => $existing,
                    'corrected' => true,
                    'previous_status' => $previousStatus,
                    'new_status' => $status,
                    'reason' => $reason,
                ];
            }

            $record = AttendanceRecord::create([
                'school_id' => $schoolId,
                'academic_year_id' => $academicYearId,
                'class_section_id' => $classSectionId,
                'student_id' => $studentId,
                'recorded_by' => $actor->id,
                'date' => $date,
                'session' => $session,
                'status' => $status,
                'note' => $note,
                'recorded_at' => now(),
            ]);

            AuditLog::create([
                'school_id' => $schoolId,
                'actor_id' => $actor->id,
                'action' => 'attendance.marked',
                'entity_type' => 'AttendanceRecord',
                'entity_id' => $record->id,
                'before_state' => null,
                'after_state' => $afterState,
            ]);

            return [
                'record' => $record,
                'corrected' => false,
            ];
        });

        if ($result['corrected']) {
            event(new AttendanceCorrected(
                $result['record'],
                $actor,
                $result['previous_status'],
                $result['new_status'],
                $result['reason'],
            ));
        } else {
            event(new AttendanceMarked($result['record'], $actor));
        }

        return $result['record'];
    }
}