<?php

namespace App\Http\Controllers\ParentPortal;

use App\Exceptions\ParentLink\InvalidParentLinkTargetFailure;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\ParentLinkRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §4 (Parent
 * onboarding)
 *
 * The one authenticated, parent-facing step in the self-service linking
 * flow — identifies a child by `student_id` (the ID-card identifier
 * IdentifierService::generateStudentId() assigns, distinct from the
 * free-form login identifier `registration_number` — see User's own doc
 * comment on that distinction), not a raw `users.id` guess, and requires
 * the child's name to match too, as a second factor against a
 * lucky/leaked student_id guess. Both checks failing look identical to
 * the caller (one generic "not found" message) — same discipline
 * AuthenticationService's own lookup failures use, so a prober can't tell
 * which half was wrong.
 *
 * Always resolves the candidate student within the acting parent's own
 * school_id — never a bare cross-school lookup — and always calls
 * ParentLinkRepository::request(), never link(): this only ever creates a
 * 'pending' row. See that method's own doc comment for why an admin
 * approval step (Admin\ParentLinkController::approve()) is the only way
 * a row created here ever becomes 'active'.
 */
class ChildLinkRequestController extends Controller
{
    public function store(Request $request, ParentLinkRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'student_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $student = User::where('school_id', $actor->school_id)
            ->where('student_id', $data['student_id'])
            ->whereHas('role', fn ($query) => $query->where('key', 'student'))
            ->first();

        if ($student === null || strcasecmp(trim($student->name), trim($data['name'])) !== 0) {
            return back()
                ->withErrors(['student_id' => 'We could not find a student matching those details at your school.'])
                ->withInput();
        }

        try {
            $repository->request($actor->school_id, $actor->id, $student->id, $actor);
        } catch (InvalidParentLinkTargetFailure $e) {
            return back()->withErrors(['student_id' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Request sent. Your school will review it.');
    }
}
