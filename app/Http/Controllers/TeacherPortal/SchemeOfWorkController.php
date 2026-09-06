<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\SchemeOfWork;
use App\Services\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6, discovery
 * §7.1, 20-phase-6-8-execution-prompt.md §3.
 *
 * Read-only: scheme_of_work has no teacher-authoring branch
 * (SchemeOfWorkRepository::create() is school_admin-only, per its own
 * doc comment — discovery §7.1's "School/admin can upload" wording),
 * so unlike TeacherPortal\LessonPlanController this controller has no
 * store(). index()/show() resolve through
 * ScopeService::tenantScope()->relationshipScope() via
 * ResolvesScopedAcademicResource — the same generic class_section_id
 * dispatch the four Phase 4 resources use (scheme_of_work's
 * class_section_id is NOT NULL, so no special-case merge is needed the
 * way LearningMaterial needs). Responses are JSON, not Blade views, for
 * this pass's read surface — building/testing Blade templates for this
 * resource is front-end work orthogonal to the authorization/scoping
 * correctness this pass is actually scoped to.
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
}
