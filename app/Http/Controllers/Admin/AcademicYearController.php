<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\AcademicYearRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 14-schoolos-implementation-plan.md §2
 *
 * Closes the gap flagged in this session's task doc: academic_years had
 * no write path outside a test fixture. Same shape as
 * EnrollmentController::store() — no route parameter (creates a row,
 * doesn't resolve one), so it falls outside row 8's single-record-by-ID
 * lint (Phase1TestGateTest) and carries no 'scope.checked'.
 *
 * school_id is never read from the request; it is always the acting
 * admin's own school_id (Ground Rule 0).
 */
class AcademicYearController extends Controller
{
    public function store(Request $request, AcademicYearRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $repository->create(
            $actor->school_id,
            $data['label'],
            $data['is_current'] ?? false
        );

        return back()->with('status', 'Academic year created.');
    }
}
