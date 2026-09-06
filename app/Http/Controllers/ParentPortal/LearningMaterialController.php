<?php

namespace App\Http\Controllers\ParentPortal;

use App\Exceptions\Communication\UnauthorizedReadReceiptFailure;
use App\Http\Controllers\Controller;
use App\Repositories\AnnouncementRepository;
use App\Repositories\ContentReadReceiptRepository;
use App\Repositories\LearningMaterialRepository;
use App\Services\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6, discovery
 * §7.3, 20-phase-6-8-execution-prompt.md §3.
 *
 * Read-only counterpart to TeacherPortal\LearningMaterialController —
 * see that class's doc comment for why this goes through
 * LearningMaterialRepository::visibleTo()/findVisibleTo() rather than
 * ResolvesScopedAcademicResource. No store() — parents never author
 * learning materials.
 *
 * markRead() (this pass's addition): see
 * StudentPortal\LearningMaterialController::markRead()'s doc comment
 * for the shared shape.
 */
class LearningMaterialController extends Controller
{
    public function index(Request $request, ScopeService $scope, LearningMaterialRepository $repository): JsonResponse
    {
        $learningMaterials = $repository->visibleTo($request->user(), $scope);

        return response()->json(['data' => $learningMaterials]);
    }

    public function show(Request $request, ScopeService $scope, LearningMaterialRepository $repository, int $learningMaterial): JsonResponse
    {
        $resolved = $repository->findVisibleTo($learningMaterial, $request->user(), $scope);

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
        LearningMaterialRepository $learningMaterials,
        int $learningMaterial
    ): JsonResponse {
        try {
            $receipt = $receipts->markRead(
                $request->user(),
                'learning_material',
                $learningMaterial,
                $scope,
                $announcements,
                $learningMaterials,
            );
        } catch (UnauthorizedReadReceiptFailure) {
            abort(404);
        }

        return response()->json(['data' => $receipt]);
    }
}
