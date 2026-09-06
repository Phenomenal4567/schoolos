<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Concerns\MarksAnnouncementAsRead;
use App\Http\Controllers\Controller;
use App\Models\ContentReadReceipt;
use App\Repositories\AnnouncementRepository;
use App\Repositories\ContentReadReceiptRepository;
use App\Services\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5,
 * 18-schoolos-communication-domain-map.md §6, §8
 *
 * Read-only. Shows 'school'-wide announcements in this parent's tenant
 * plus 'class_section' announcements for their linked children's
 * sections, via AnnouncementRepository::visibleTo() and
 * ScopeService::parentRelationshipScope()'s existing class_section_id
 * branch — no store(), parents never author announcements.
 *
 * show()/markRead() (this pass's addition): see
 * TeacherPortal\AnnouncementController's doc comment for the shape
 * shared across all three portals.
 */
class AnnouncementController extends Controller
{
    use MarksAnnouncementAsRead;

    public function index(Request $request, ScopeService $scope, AnnouncementRepository $repository): View
    {
        $actor = $request->user();
        $announcements = $repository->visibleTo($actor, $scope);

        // View-only read-status lookup — see
        // TeacherPortal\AnnouncementController::index()'s own doc
        // comment for why this is a direct ContentReadReceipt query
        // rather than a repository method.
        $readIds = ContentReadReceipt::where('user_id', $actor->id)
            ->where('entity_type', 'announcement')
            ->whereIn('entity_id', $announcements->pluck('id'))
            ->pluck('entity_id')
            ->all();

        return view('parent.announcements.index', [
            'announcements' => $announcements,
            'readIds' => $readIds,
        ]);
    }

    public function show(Request $request, ScopeService $scope, AnnouncementRepository $repository, int $announcement): View
    {
        $actor = $request->user();
        $resolved = $repository->findVisibleTo($announcement, $actor, $scope);

        if ($resolved === null) {
            abort(404);
        }

        $isRead = ContentReadReceipt::where('user_id', $actor->id)
            ->where('entity_type', 'announcement')
            ->where('entity_id', $resolved->id)
            ->exists();

        return view('parent.announcements.show', [
            'announcement' => $resolved,
            'isRead' => $isRead,
        ]);
    }

    public function markRead(
        Request $request,
        ScopeService $scope,
        ContentReadReceiptRepository $receipts,
        AnnouncementRepository $announcements,
        int $announcement
    ): JsonResponse {
        $receipt = $this->markAnnouncementRead($request, $scope, $receipts, $announcements, $announcement);

        return response()->json(['data' => $receipt]);
    }
}
