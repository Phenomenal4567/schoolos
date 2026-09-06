<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §8,
 * 20-phase-6-8-execution-prompt.md §6 (Track 7b)
 *
 * Read-only, own/linked-children's promotion records only —
 * ScopeService::relationshipScope()'s existing generic student_id
 * dispatch covers this for free (Promotion's own doc comment). Same
 * index()/show() shape as ParentPortal\ExamController.
 */
class PromotionController extends Controller
{
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $promotions = $scope
            ->relationshipScope($scope->tenantScope(Promotion::query(), $actor), $actor, Promotion::class)
            ->with(['student', 'fromClassSection.standard', 'fromClassSection.section', 'toClassSection.standard', 'toClassSection.section'])
            ->latest('decided_at')
            ->get();

        return view('parent.promotions.index', ['promotions' => $promotions]);
    }

    public function show(Request $request, ScopeService $scope, int $promotion): View
    {
        $actor = $request->user();

        $resolved = $scope
            ->relationshipScope($scope->tenantScope(Promotion::query(), $actor), $actor, Promotion::class)
            ->with(['student', 'fromClassSection.standard', 'fromClassSection.section', 'toClassSection.standard', 'toClassSection.section'])
            ->find($promotion);

        if ($resolved === null) {
            abort(404);
        }

        return view('parent.promotions.show', ['promotion' => $resolved]);
    }
}