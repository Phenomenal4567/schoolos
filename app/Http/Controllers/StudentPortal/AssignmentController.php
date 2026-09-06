<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Repositories\AssignmentSubmissionRepository;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §5, §6
 *
 * Read-only counterpart to TeacherPortal\AssignmentController for the
 * Assignment resource itself, plus one write action this pass adds:
 * submit(). A student may submit against an Assignment they can already
 * read — the same scope submit() re-checks server-side via
 * AssignmentSubmissionRepository::submit() rather than trusting that
 * show()'s earlier 200 still holds by the time the submit request
 * arrives. Grading remains TeacherPortal's action, not this
 * controller's — see that controller's grade() method.
 */
class AssignmentController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $assignments = $this->scopedIndex($request, $scope, Assignment::class, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        return view('student.assignments.index', ['assignments' => $assignments]);
    }

    /**
     * Also loads this student's own submission for this assignment (if
     * any) so the show page can render either the submission form or
     * the already-submitted/graded state — view-only, a direct
     * AssignmentSubmission query scoped to this student's own id, the
     * same shape submit()'s own repository call re-checks against
     * server-side rather than trusting an earlier read.
     */
    public function show(Request $request, ScopeService $scope, int $assignment): View
    {
        $actor = $request->user();

        $resolved = $this->scopedFind($request, $scope, Assignment::class, $assignment, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        if ($resolved === null) {
            abort(404);
        }

        $submission = AssignmentSubmission::where('assignment_id', $resolved->id)
            ->where('student_id', $actor->id)
            ->first();

        return view('student.assignments.show', [
            'assignment' => $resolved,
            'submission' => $submission,
        ]);
    }

    /**
     * The submission form's own submit target on the assignment show
     * page — a plain `<form method="POST">`, so a browser caller gets
     * back()->with('status') the same way every other web-form write
     * action in this app does, while any fetch()/API caller keeps the
     * original JSON 201 contract.
     */
    public function submit(Request $request, AssignmentSubmissionRepository $repository, int $assignment): Response
    {
        $actor = $request->user();

        $data = $request->validate([
            'comments' => ['nullable', 'string'],
        ]);

        try {
            $submission = $repository->submit($actor, $assignment, $data['comments'] ?? null);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $submission], 201);
        }

        return back()->with('status', 'Assignment submitted.');
    }
}
