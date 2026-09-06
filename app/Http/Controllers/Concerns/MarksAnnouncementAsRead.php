<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\Communication\UnauthorizedReadReceiptFailure;
use App\Models\ContentReadReceipt;
use App\Repositories\AnnouncementRepository;
use App\Repositories\ContentReadReceiptRepository;
use App\Services\ScopeService;
use Illuminate\Http\Request;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §6, §8
 *
 * Shared by TeacherPortal/ParentPortal/StudentPortal's AnnouncementController
 * classes — all three have the identical markRead() shape (mark the
 * announcement this route's {announcement} parameter resolves to as read
 * for the acting user, 404 on a miss), so it's written once here rather
 * than three times, matching HasNotificationInbox's own reasoning for
 * why the identical notifications-index shape lives in a trait instead
 * of three copies.
 *
 * Aborts 404 itself rather than returning null — unlike
 * ResolvesScopedAcademicResource::scopedFind(), this trait has only one
 * caller shape per controller (a single markRead() action, not an
 * index()/show() pair with different miss-handling needs), so there's no
 * reason to push the abort() call back out to three call sites.
 */
trait MarksAnnouncementAsRead
{
    protected function markAnnouncementRead(
        Request $request,
        ScopeService $scope,
        ContentReadReceiptRepository $receipts,
        AnnouncementRepository $announcements,
        int $announcementId
    ): ContentReadReceipt {
        try {
            return $receipts->markRead($request->user(), 'announcement', $announcementId, $scope, $announcements);
        } catch (UnauthorizedReadReceiptFailure) {
            abort(404);
        }
    }
}
