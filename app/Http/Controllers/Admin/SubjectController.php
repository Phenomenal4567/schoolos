<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\SubjectRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 15-academic-domain-map.md §2
 *
 * Closes the gap flagged in this session's task doc: subjects had no
 * write path outside a test fixture, even though
 * ClassTeacherAssignmentController already validates subject_id against
 * this table (Rule::exists('subjects', 'id')) — that dropdown had
 * nothing real to populate before this. Same shape as
 * StandardController::store() / SectionController::store() — no route
 * parameter, no 'scope.checked'. school_id is never read from the
 * request.
 */
class SubjectController extends Controller
{
    public function store(Request $request, SubjectRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
        ]);

        $repository->create($actor->school_id, $data['name'], $data['code'] ?? null);

        return back()->with('status', 'Subject created.');
    }
}
