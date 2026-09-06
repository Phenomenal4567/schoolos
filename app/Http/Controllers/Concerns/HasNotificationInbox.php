<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5
 *
 * Shared by TeacherPortal/ParentPortal/StudentPortal's NotificationController
 * classes — all three have the identical shape (an actor's own
 * notifications, newest first), so it's written once here rather than
 * three times, the same reasoning ResolvesScopedAcademicResource already
 * documents for the four generic Phase 4 resources.
 *
 * Deliberately no ScopeService involvement: $request->user()->notifications()
 * is Laravel's own Notifiable relation, constrained to notifiable_id =
 * this exact authenticated user's id by construction — there is no
 * tenant or relationship question to ask here the way there is for a
 * resource resolved by a route-supplied id (see the notifications
 * migration's own doc comment on why this table carries no school_id).
 *
 * Paginated (20/page, matching AnnouncementRepository::visibleTo()'s own
 * default) rather than ->get(): an inbox accumulates one row per
 * absence/announcement/etc. for as long as the account exists, so it has
 * the same unbounded-growth shape visibleTo() was paginated for — see
 * that method's doc comment.
 */
trait HasNotificationInbox
{
    private const PER_PAGE = 20;

    protected function ownNotifications(Request $request, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $request->user()->notifications()->latest()->paginate($perPage);
    }
}
