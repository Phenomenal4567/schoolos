<?php

namespace App\Notifications;

use App\Models\AttendanceRecord;
use Illuminate\Notifications\Notification;

/**
 * Design ref: 09-attendance-map.md §5, 14-schoolos-implementation-plan.md §3, §5
 *
 * Phase 3's AttendanceMarked event doc comment flagged this exact
 * mechanism as deliberately deferred to Communication: "GegoK12's
 * real-time per-parent notification fan-out on attendance ... confirmed
 * a genuinely good pattern ... worth keeping." This notification plus
 * NotifyParentsOfAbsence (the listener that sends it) is that pattern,
 * reimplemented as a decoupled listener rather than GegoK12's inline
 * dispatch — the thing 09 §5 said was worth *not* copying was the
 * coupling, not the fan-out itself.
 *
 * database channel only, same reasoning as
 * AnnouncementPublishedNotification's doc comment.
 */
class AttendanceAbsenceNotification extends Notification
{
    public function __construct(
        private readonly AttendanceRecord $record,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'attendance.absent',
            'attendance_record_id' => $this->record->id,
            'school_id' => $this->record->school_id,
            'student_id' => $this->record->student_id,
            'class_section_id' => $this->record->class_section_id,
            'date' => $this->record->date,
            'session' => $this->record->session,
        ];
    }
}
