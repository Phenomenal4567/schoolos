<?php

namespace App\Repositories;

use App\Events\SubjectAttendanceCorrected;
use App\Events\SubjectAttendanceMarked;
use App\Exceptions\Attendance\StudentNotEnrolledFailure;
use App\Models\AuditLog;
use App\Models\StudentEnrollment;
use App\Models\SubjectAttendanceCorrection;
use App\Models\SubjectAttendanceRecord;
use App\Models\SubjectAttendanceTopic;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
 *
 * The write path for subject_attendance_records + subject_attendance_topics.
 * mark() mirrors AttendanceRepository::mark() almost exactly — see that
 * class's doc comment for the shared reasoning (upsert-not-precheck
 * against the schema-level unique constraint, audit_logs + a dedicated
 * corrections table, events dispatched only after commit) — with one
 * structural difference: instead of a caller-supplied class_section_id +
 * session enum, mark() is keyed by $timetableSlotId, and derives
 * class_section_id/subject_id from that slot. This is deliberate: a
 * subject-attendance row's identity is "which timetabled period, which
 * date", not "which class, which enum session" — the timetable_slot
 * already carries the subject and the teacher who owns that period (see
 * TimetableSlot's own doc comment), so there's no separate subject_id/
 * class_section_id caller input to keep consistent with it.
 */
class SubjectAttendanceRepository
{
    /**
     * @throws StudentNotEnrolledFailure if $studentId has no active
     *         student_enrollments row for the slot's class_section_id.
     * @throws \InvalidArgumentException if $timetableSlotId doesn't belong
     *         to $schoolId or $academicYearId, or if this call corrects
     *         an existing row without a $reason.
     */
    public function mark(
        int $schoolId,
        int $academicYearId,
        int $timetableSlotId,
        int $studentId,
        string $date,
        string $status,
        User $actor,
        ?string $note = null,
        ?string $reason = null
    ): SubjectAttendanceRecord {
        $slot = TimetableSlot::findOrFail($timetableSlotId);

        if ($slot->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "SubjectAttendanceRepository::mark(): timetable_slot #{$timetableSlotId} belongs to "
                . "school #{$slot->school_id}, not the requested school #{$schoolId}."
            );
        }

        if ($slot->academic_year_id !== $academicYearId) {
            throw new \InvalidArgumentException(
                "SubjectAttendanceRepository::mark(): timetable_slot #{$timetableSlotId} belongs to "
                . "academic year #{$slot->academic_year_id}, not the requested academic year #{$academicYearId}."
            );
        }

        $enrollment = StudentEnrollment::where('student_id', $studentId)
            ->where('class_section_id', $slot->class_section_id)
            ->where('status', 'active')
            ->first();

        if ($enrollment === null) {
            $student = User::findOrFail($studentId);

            throw new StudentNotEnrolledFailure($student, $slot->class_section_id);
        }

        $result = DB::transaction(function () use (
            $schoolId,
            $slot,
            $studentId,
            $date,
            $status,
            $actor,
            $note,
            $reason
        ) {
            // Locks the row for the duration of the transaction — same
            // TOCTOU-safe pattern AttendanceRepository::mark() uses.
            $existing = SubjectAttendanceRecord::where('student_id', $studentId)
                ->where('date', $date)
                ->where('timetable_slot_id', $slot->id)
                ->lockForUpdate()
                ->first();

            $afterState = [
                'timetable_slot_id' => $slot->id,
                'status' => $status,
                'note' => $note,
            ];

            if ($existing !== null) {
                if ($reason === null || trim($reason) === '') {
                    throw new \InvalidArgumentException(
                        'SubjectAttendanceRepository::mark(): correcting an existing '
                        . "subject_attendance_records row (#{$existing->id}) requires a reason — "
                        . 'subject_attendance_corrections.reason is NOT NULL at the schema level.'
                    );
                }

                $beforeState = [
                    'timetable_slot_id' => $existing->timetable_slot_id,
                    'status' => $existing->status,
                    'note' => $existing->note,
                ];
                $previousStatus = $existing->status;

                $existing->fill([
                    'recorded_by' => $actor->id,
                    'status' => $status,
                    'note' => $note,
                    'recorded_at' => now(),
                ]);
                $existing->save();

                SubjectAttendanceCorrection::create([
                    'subject_attendance_record_id' => $existing->id,
                    'previous_status' => $previousStatus,
                    'new_status' => $status,
                    'corrected_by' => $actor->id,
                    'corrected_at' => now(),
                    'reason' => $reason,
                ]);

                AuditLog::create([
                    'school_id' => $schoolId,
                    'actor_id' => $actor->id,
                    'action' => 'subject_attendance.corrected',
                    'entity_type' => 'SubjectAttendanceRecord',
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

            $record = SubjectAttendanceRecord::create([
                'school_id' => $schoolId,
                'academic_year_id' => $slot->academic_year_id,
                'class_section_id' => $slot->class_section_id,
                'subject_id' => $slot->subject_id,
                'timetable_slot_id' => $slot->id,
                'student_id' => $studentId,
                'recorded_by' => $actor->id,
                'date' => $date,
                'status' => $status,
                'note' => $note,
                'recorded_at' => now(),
            ]);

            AuditLog::create([
                'school_id' => $schoolId,
                'actor_id' => $actor->id,
                'action' => 'subject_attendance.marked',
                'entity_type' => 'SubjectAttendanceRecord',
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
            event(new SubjectAttendanceCorrected(
                $result['record'],
                $actor,
                $result['previous_status'],
                $result['new_status'],
                $result['reason'],
            ));
        } else {
            event(new SubjectAttendanceMarked($result['record'], $actor));
        }

        return $result['record'];
    }

    /**
     * Upserts the topic-taught fact for one timetable_slot on one date —
     * see SubjectAttendanceTopic's doc comment for why this is a separate
     * upsert rather than a field on mark(). $schoolId is the caller's own
     * scoped context, never client input directly, per Ground Rule 0.
     *
     * @throws \InvalidArgumentException if $timetableSlotId doesn't
     *         belong to $schoolId.
     */
    public function recordTopic(
        int $schoolId,
        int $timetableSlotId,
        string $date,
        string $topic,
        User $actor
    ): SubjectAttendanceTopic {
        $slot = TimetableSlot::findOrFail($timetableSlotId);

        if ($slot->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "SubjectAttendanceRepository::recordTopic(): timetable_slot #{$timetableSlotId} belongs "
                . "to school #{$slot->school_id}, not the requested school #{$schoolId}."
            );
        }

        $record = SubjectAttendanceTopic::updateOrCreate(
            ['timetable_slot_id' => $slot->id, 'date' => $date],
            [
                'school_id' => $schoolId,
                'class_section_id' => $slot->class_section_id,
                'subject_id' => $slot->subject_id,
                'topic' => $topic,
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]
        );

        AuditLog::create([
            'school_id' => $schoolId,
            'actor_id' => $actor->id,
            'action' => 'subject_attendance.topic_recorded',
            'entity_type' => 'SubjectAttendanceTopic',
            'entity_id' => $record->id,
            'before_state' => null,
            'after_state' => ['timetable_slot_id' => $slot->id, 'date' => $date, 'topic' => $topic],
        ]);

        return $record;
    }
}
