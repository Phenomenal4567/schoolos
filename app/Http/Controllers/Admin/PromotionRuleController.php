<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\PromotionRuleRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §8,
 * 20-phase-6-8-execution-prompt.md §6 (Track 7b)
 *
 * school_admin's authoring surface for promotion_rules — store-only,
 * matching this bundle's admin-write-path-focused convention (see
 * Admin\CalendarEventController's own doc comment for the shared
 * reasoning). No route parameter (setRule() upserts by school/year/
 * standard from the request body, not by an existing row's id), exempt
 * from row 8's scope-check lint the same way calendar-events.store is.
 */
class PromotionRuleController extends Controller
{
    public function store(Request $request, PromotionRuleRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'standard_id' => ['required', 'integer'],
            'min_aggregate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $repository->setRule(
                $actor->school_id,
                $data['academic_year_id'],
                $data['standard_id'],
                ['min_aggregate' => (float) $data['min_aggregate']],
                $actor
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['promotion_rule' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Promotion rule saved.');
    }
}