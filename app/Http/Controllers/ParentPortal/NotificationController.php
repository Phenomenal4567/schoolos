<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Concerns\HasNotificationInbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5
 *
 * See HasNotificationInbox's doc comment for the shape shared across all
 * three portals' NotificationController classes. This is the endpoint a
 * parent's absence notifications (NotifyParentsOfAbsence) and
 * announcement notifications (NotifyAudienceOfAnnouncement) both land in.
 */
class NotificationController extends Controller
{
    use HasNotificationInbox;

    public function index(Request $request): View
    {
        return view('parent.notifications.index', [
            'notifications' => $this->ownNotifications($request),
        ]);
    }
}
