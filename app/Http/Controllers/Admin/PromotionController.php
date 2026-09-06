<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Promotion\MissingPromotionReasonFailure;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Promotion;
use App\Models\PromotionRule;
use App\Models\StudentEnrollment;
use App\Repositories\PromotionRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §8,
 * 20-phase-6-8-execution-prompt.md §6 (Track 7b)
 *
 * school_admin's read + decide surface for promotions. index is an
 * id-less tenant-scoped listing (school_admin is tenant-scope-only by
 * default per ScopeService's own doc comment, so a plain tenantScope()
 * call is the whole read side here — no relationshipScope() call,
 * matching every other admin-only resource in this group).
 * run-automatic/override both resolve their targets from *body*
 * parameters (class_section_id / promotion_rule_id /
 * student_enrollment_id), not a route parameter — the same shape
 * TeacherPortal\ExamController::recordMark() uses for exam_id/
 * student_id, exempt from row 8's 'scope.checked' lint for the same
 * reason (no route parameter to check; cross-tenant validation happens
 * inside PromotionRepository instead, via its own $schoolId guard on
 * every resolved model).
 */
class PromotionController extends Controller
{
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $promotions = $scope->tenantScope(Promotion::query(), $actor)
            ->with(['student', 'fromClassSection.standard', 'fromClassSection.section', 'toClassSection.standard', 'toClassSection.section', 'decidedBy'])
            ->latest('decided_at')
            ->get();

        return view('admin.promotions.index', ['promotions' => $promotions]);
    }

    public function runAutomatic(Request $request, PromotionRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'from_class_section_id' => ['required', 'integer'],
            'to_class_section_id' => ['nullable', 'integer'],
            'promotion_rule_id' => ['required', 'integer'],
        ]);

        $fromSection = ClassSection::find($data['from_class_section_id']);
        $toSection = ! empty($data['to_class_section_id'])
            ? ClassSection::find($data['to_class_section_id'])
            : null;
        $rule = PromotionRule::find($data['promotion_rule_id']);

        if ($fromSection === null || $rule === null || (! empty($data['to_class_section_id']) && $toSection === null)) {
            return back()->withErrors(['promotion' => 'One or more referenced records were not found.']);
        }

        try {
            $results = $repository->runAutomatic($actor->school_id, $fromSection, $toSection, $rule, $actor);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['promotion' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "Automatic promotion evaluated {$results->count()} student(s).");
    }

    /**
     * Design ref: SchoolOS Onboarding & Authentication UI, "Academic
     * Setup" (Manual Promotion toggle).
     *
     * $actor->school->allow_manual_promotion defaults true (migration
     * default, not the model) — every school that existed before this
     * column, or that skips the toggle via SuperAdmin\SchoolController,
     * keeps today's behavior unchanged. Checked here, not in
     * PromotionRepository::override(), matching this bundle's pattern of
     * repositories enforcing data invariants and controllers enforcing
     * this kind of school-level policy switch.
     */
    public function override(Request $request, PromotionRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor->school->allow_manual_promotion) {
            return back()->withErrors(['promotion' => 'Manual promotion is disabled for this school.']);
        }

        $data = $request->validate([
            'student_enrollment_id' => ['required', 'integer'],
            'to_class_section_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string'],
        ]);

        $enrollment = StudentEnrollment::find($data['student_enrollment_id']);
        $toSection = ! empty($data['to_class_section_id'])
            ? ClassSection::find($data['to_class_section_id'])
            : null;

        if ($enrollment === null || (! empty($data['to_class_section_id']) && $toSection === null)) {
            return back()->withErrors(['promotion' => 'One or more referenced records were not found.']);
        }

        try {
            $repository->override($actor->school_id, $enrollment, $toSection, $data['reason'], $actor);
        } catch (MissingPromotionReasonFailure|\InvalidArgumentException $e) {
            return back()->withErrors(['promotion' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Promotion decision recorded.');
    }
}