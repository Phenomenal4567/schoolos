<?php

namespace App\Http\Controllers\StudentPortal;

use App\Exceptions\Communication\UnauthorizedReadReceiptFailure;
use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\SchemeOfWork;
use App\Repositories\AnnouncementRepository;
use App\Repositories\ContentReadReceiptRepository;
use App\Services\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6, discovery
 * §7.1, 20-phase-6-8-execution-prompt.md §3.
 *
 * Read-only counterpart to TeacherPortal\SchemeOfWorkController — see
 * that class's doc comment for the shared index()/show() shape. No
 * store() — students never author scheme_of_work.
 *
 * markRead() (this pass's addition, per 19 §6's "tracking whether
 * students/parents have opened them"): follows
 * StudentPortal\AnnouncementController::markRead()'s shape —
 * ContentReadReceiptRepository::markRead() always requires an
 * AnnouncementRepository argument even for a non-announcement
 * entity_type (see that repository's own signature), so it's injected
 * and passed here regardless.
 */
class SchemeOfWorkController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): JsonResponse
    {
        $schemesOfWork = $this->scopedIndex(
            $request,
            $scope,
            SchemeOfWork::class,
            ['classSection.standard', 'classSection.section', 'subject', 'academicTerm', 'uploadedBy'],
        );

        return response()->json(['data' => $schemesOfWork]);
    }

    public function show(Request $request, ScopeService $scope, int $schemeOfWork): JsonResponse
    {
        $resolved = $this->scopedFind(
            $request,
            $scope,
            SchemeOfWork::class,
            $schemeOfWork,
            ['classSection.standard', 'classSection.section', 'subject', 'academicTerm', 'uploadedBy'],
        );

        if ($resolved === null) {
            abort(404);
        }

        return response()->json(['data' => $resolved]);
    }

    public function markRead(
        Request $request,
        ScopeService $scope,
        ContentReadReceiptRepository $receipts,
        AnnouncementRepository $announcements,
        int $schemeOfWork
    ): JsonResponse {
        try {
            $receipt = $receipts->markRead($request->user(), 'scheme_of_work', $schemeOfWork, $scope, $announcements);
        } catch (UnauthorizedReadReceiptFailure) {
            abort(404);
        }

        return response()->json(['data' => $receipt]);
    }
}
