<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Http\Controllers\Concerns\MarksAnnouncementAsRead;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\ContentReadReceipt;
use App\Repositories\AnnouncementRepository;
use App\Repositories\ContentReadReceiptRepository;
use App\Services\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5,
 * 18-schoolos-communication-domain-map.md §6, §8
 *
 * index() shows every announcement AnnouncementRepository::visibleTo()
 * resolves for this teacher — 'school'-wide announcements in their
 * tenant plus 'class_section' announcements for sections they're
 * assigned to (via ScopeService's existing teacherRelationshipScope()).
 * store() delegates authoring rules (class_section-only, must be
 * assigned) entirely to AnnouncementRepository::create(); a
 * TeacherNotAssignedToSectionFailure becomes 404, never 403, matching
 * every other TeacherPortal store() in this bundle (see
 * TeacherPortal\LessonPlanController's doc comment).
 *
 * show()/markRead() (this pass's addition, closing 18 §8's "add
 * per-Announcement show() endpoints" item): show() resolves through the
 * identical visibility rule index() already uses, one row at a time, via
 * AnnouncementRepository::findVisibleTo() — 404-not-403 on a miss, same
 * discipline as every other by-ID resolution in this bundle. markRead()
 * is the "mark as read" endpoint 18 §8 named show() as the natural home
 * for — a separate POST rather than an implicit side effect of show(),
 * so a client can list/preview announcements without silently marking
 * every one of them read.
 */
class AnnouncementController extends Controller
{
    use MarksAnnouncementAsRead;

    public function index(Request $request, ScopeService $scope, AnnouncementRepository $repository): View
    {
        $actor = $request->user();
        $announcements = $repository->visibleTo($actor, $scope);

        // View-only read-status lookup — ContentReadReceiptRepository has
        // no "read status for a list of ids" method of its own (its
        // markRead() is single-row, write-only), so this queries
        // ContentReadReceipt directly for presentation, the same way
        // AttendanceController::show()'s $records lookup keys a
        // collection by id for its view without going through a
        // repository. Doesn't affect which announcements are visible —
        // that's still entirely visibleTo()'s own result above.
        $readIds = ContentReadReceipt::where('user_id', $actor->id)
            ->where('entity_type', 'announcement')
            ->whereIn('entity_id', $announcements->pluck('id'))
            ->pluck('entity_id')
            ->all();

        // View-only data for the "Post to my class" form below — see
        // TeacherPortal\TimetableController::index()'s own doc comment
        // for the identical reasoning (offering valid class_section_id
        // options for store(), not changing what store() itself checks).
        $classSections = $scope
            ->relationshipScope(
                $scope->tenantScope(ClassSection::query(), $actor),
                $actor,
                ClassSection::class
            )
            ->with(['standard', 'section'])
            ->get();

        return view('teacher.announcements.index', [
            'announcements' => $announcements,
            'readIds' => $readIds,
            'classSections' => $classSections,
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

        return view('teacher.announcements.show', [
            'announcement' => $resolved,
            'isRead' => $isRead,
        ]);
    }

    /**
     * The "Post to my class" HTML form's own submit target (a plain
     * `<form method="POST">`, no fetch()) — see
     * TeacherPortal\TimetableController::store()'s doc comment for why
     * both the caught InvalidArgumentException and the success path now
     * branch on wantsJson() rather than always returning JSON: a browser
     * form submit needs withErrors()/withInput() and a redirect the way
     * every other web-form store() in this app behaves, while any
     * fetch()/API caller keeps the original JSON 201/422 contract.
     */
    public function store(Request $request, AnnouncementRepository $repository): Response
    {
        $actor = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'class_section_id' => ['required', 'integer'],
        ]);

        try {
            $announcement = $repository->create(
                $actor->school_id,
                $actor,
                $data['title'],
                $data['body'],
                'class_section',
                $data['class_section_id'],
            );
        } catch (TeacherNotAssignedToSectionFailure) {
            abort(404);
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['class_section_id' => $e->getMessage()]], 422);
            }

            return back()->withErrors(['class_section_id' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $announcement], 201);
        }

        return redirect()->route('teacher.announcements.index')->with('status', 'Announcement posted.');
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