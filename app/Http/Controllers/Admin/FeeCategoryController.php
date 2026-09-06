<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\FeeCategoryRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 22-schoolos-finance-schema.md §1 (discovery §10.1).
 *
 * school_admin/accountant's authoring surface for fee_categories —
 * store-only, redirect-back, matching this whole finance block's
 * View/RedirectResponse convention (Admin\FeeAssessmentController's own
 * doc comment). The "Add fee category" form lives on
 * admin.fee-assessments.index (there is no dedicated categories page),
 * the same way ParentPortal\FeedbackController's "Send feedback" form
 * lives on that controller's own index page rather than a separate
 * route. $actor->school_id is the only source of school_id (Ground
 * Rule 0). No route parameter, so exempt from Phase1TestGateTest's row
 * 8 lint the same way announcements.store/subjects.store are.
 */
class FeeCategoryController extends Controller
{
    public function store(Request $request, FeeCategoryRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
        ]);

        try {
            $repository->create($actor->school_id, $data['key'], $data['label']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['key' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.fee-assessments.index')->with('status', 'Fee category added.');
    }
}
