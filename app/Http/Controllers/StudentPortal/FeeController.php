<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\FeeAssessment;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 21-schoolos-finance-architecture.md §5,
 * 22-schoolos-finance-schema.md §6 (discovery §11).
 *
 * Read-only fee view for the acting student's own fee_assessments.
 * Discovery §11 only names a *Parent* Fee Portal, but 20 §5's kickoff
 * prompt lists a student-facing self-only variant as "any future
 * student-facing fee view" (21 §5) — the same student/parent split this
 * codebase already keeps for timetable/exams/announcements/feedback, so
 * building it now rather than leaving fees as the one Phase-4-style
 * resource without a student view.
 *
 * ResolvesScopedAcademicResource works unmodified here, same as
 * ParentPortal\FeeController's own doc comment: FeeAssessment carries
 * student_id directly, so ScopeService::relationshipScope()'s existing
 * generic student_id-column dispatch applies with no new ScopeService
 * code. show() resolves one row by route parameter, so it carries
 * 'scope.checked' at the route table, and 404-not-403 on a miss, same
 * discipline as every other by-ID route in this bundle. No store() —
 * students never initiate payments (21 §5's parent-only write path);
 * that stays ParentPortal\PaymentController's surface alone.
 */
class FeeController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $assessments = $this->scopedIndex($request, $scope, FeeAssessment::class, ['feeCategory', 'student']);

        return view('student.fees.index', ['fees' => $assessments]);
    }

    public function show(Request $request, ScopeService $scope, int $feeAssessment): View
    {
        $resolved = $this->scopedFind($request, $scope, FeeAssessment::class, $feeAssessment, ['feeCategory', 'student']);

        if ($resolved === null) {
            abort(404);
        }

        return view('student.fees.show', [
            'assessment' => $resolved,
        ]);
    }
}
