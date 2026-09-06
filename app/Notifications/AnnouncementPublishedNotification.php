<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Notification;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5
 *
 * Database-channel only for this pass — no mail/broadcast channel is
 * wired, matching the plan's own phasing (email/push channels are a
 * follow-up, this pass is the in-app inbox). `via()` returning a single
 * channel rather than a caller-supplied list keeps that decision in one
 * place rather than letting each dispatch site choose channels
 * independently.
 *
 * Recipient-agnostic: the same payload shape is sent to every recipient
 * (student, parent, or teacher) — there's no per-recipient branching
 * here, unlike AttendanceAbsenceNotification, because an announcement's
 * content doesn't differ by who's reading it the way "your child was
 * marked absent" is inherently parent-specific.
 */
class AnnouncementPublishedNotification extends Notification
{
    public function __construct(
        private readonly Announcement $announcement,
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
            'type' => 'announcement.published',
            'announcement_id' => $this->announcement->id,
            'school_id' => $this->announcement->school_id,
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'audience_type' => $this->announcement->audience_type,
            'class_section_id' => $this->announcement->class_section_id,
            'author_id' => $this->announcement->author_id,
        ];
    }
}
