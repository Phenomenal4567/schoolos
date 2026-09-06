<?php

namespace App\Http\Controllers\ParentPortal;

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
 * store() — parents never author scheme_of_work.
 *
 * markRead() (this pass's addition): see
 * StudentPortal\SchemeOfWorkController::markRead()'s doc comment for
 * the shape shared across both.
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
