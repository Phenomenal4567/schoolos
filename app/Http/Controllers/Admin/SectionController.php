<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\SectionRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 14-schoolos-implementation-plan.md §2
 *
 * Closes the gap flagged in this session's task doc: sections had no
 * write path outside a test fixture. Same shape as
 * AcademicYearController::store() / StandardController::store() — no
 * route parameter, no 'scope.checked'. school_id is never read from the
 * request.
 */
class SectionController extends Controller
{
    public function store(Request $request, SectionRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $repository->create($actor->school_id, $data['name']);

        return back()->with('status', 'Section created.');
    }
}
