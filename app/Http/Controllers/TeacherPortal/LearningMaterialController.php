<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Http\Controllers\Controller;
use App\Repositories\LearningMaterialRepository;
use App\Services\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6, discovery
 * §7.3, 20-phase-6-8-execution-prompt.md §3.
 *
 * Cannot use ResolvesScopedAcademicResource — learning_materials'
 * class_section_id is nullable, and that trait's generic dispatch would
 * silently hide every school-wide row (see
 * LearningMaterialRepository's own doc comment). index() calls
 * LearningMaterialRepository::visibleTo(), show() calls
 * findVisibleTo(), 404-not-403 on a miss. No store() — matches
 * SchemeOfWorkController's read-only shape; learning material authoring
 * is school_admin-only.
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
}
