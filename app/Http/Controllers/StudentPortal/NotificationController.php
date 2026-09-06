<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Concerns\HasNotificationInbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5
 *
 * See HasNotificationInbox's doc comment for the shape shared across all
 * three portals' NotificationController classes.
 */
class NotificationController extends Controller
{
    use HasNotificationInbox;

    public function index(Request $request): View
    {
        return view('student.notifications.index', [
            'notifications' => $this->ownNotifications($request),
        ]);
    }
}
