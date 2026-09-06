<?php

namespace App\Notifications;

use App\Events\TimetableSlotChanged;
use Illuminate\Notifications\Notification;

/**
 * Design ref: Timetable Management module.
 *
 * Database-channel only, matching AnnouncementPublishedNotification's
 * own phasing note — no mail/broadcast channel wired for this pass.
 */
class TimetableSlotChangedNotification extends Notification
{
    public function __construct(
        private readonly TimetableSlotChanged $event,
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
        $slot = $this->event->slot;

        return [
            'type' => 'timetable.' . $this->event->action,
            'timetable_slot_id' => $slot?->id,
            'day_of_week' => $slot?->day_of_week,
            'period_number' => $slot?->period_number,
            'class_section_id' => $slot?->class_section_id,
            'subject_id' => $slot?->subject_id,
            'message' => match ($this->event->action) {
                'created' => 'A new timetable slot was added to your schedule.',
                'updated' => 'One of your timetable slots was changed.',
                'deleted' => 'A timetable slot was removed from your schedule.',
                default => 'Your timetable changed.',
            },
        ];
    }
}
