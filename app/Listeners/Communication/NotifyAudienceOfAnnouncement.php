<?php

namespace App\Listeners\Communication;

use App\Events\AnnouncementPublished;
use App\Notifications\AnnouncementPublishedNotification;
use App\Services\AnnouncementAudienceResolver;
use Illuminate\Support\Facades\Notification;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5
 *
 * Registered against AnnouncementPublished in AppServiceProvider::boot() —
 * explicit Event::listen() rather than relying on Laravel's
 * convention-based auto-discovery, matching this bundle's general
 * preference for wiring that's visible at a single call site (see
 * AppServiceProvider's own doc comment) rather than implicit by
 * directory structure.
 *
 * Notification::send() rather than a per-user loop calling
 * $user->notify(): one bulk dispatch, same reasoning
 * AnnouncementAudienceResolver gives for returning a Collection instead
 * of issuing notifications itself — resolving the audience and notifying
 * it are two separate steps, this listener is what joins them.
 */
class NotifyAudienceOfAnnouncement
{
    public function __construct(
        private readonly AnnouncementAudienceResolver $resolver,
    ) {
    }

    public function handle(AnnouncementPublished $event): void
    {
        $recipients = $this->resolver->resolve($event->announcement);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new AnnouncementPublishedNotification($event->announcement));
    }
}
