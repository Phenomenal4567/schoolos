<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ParentLink\InvalidParentLinkTargetFailure;
use App\Http\Controllers\Controller;
use App\Models\StudentParentLink;
use App\Repositories\ParentLinkRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Design ref: 14-schoolos-implementation-plan.md §2
 *
 * First controller/route calling ParentLinkRepository — same "confirm,
 * don't assume this was the intended next step" caveat as
 * EnrollmentController. ParentLinkRepository's own doc comment describes
 * "admin-initiated, not self-service" as a statement about which
 * controller is allowed to call link() — this is that controller; there
 * is deliberately no parent-facing self-linking route anywhere in this
 * codebase.
 *
 * store() has no route parameter (creates/reactivates a row, doesn't
 * resolve one) and carries no 'scope.checked'. destroy() resolves an
 * existing student_parent_links row by ID, so it does carry
 * 'scope.checked' and resolves {parentLink} through
 * ScopeService::tenantScope() first — a link belonging to another
 * school 404s rather than reaching unlink() at all. unlink() itself
 * takes only a bare $linkId with no school parameter (see its own doc
 * comment), so this tenant check is the only thing standing between an
 * admin and unlinking a record from a school that isn't theirs; it is
 * not optional defense-in-depth the way DashboardController's role_id
 * filter is.
 */
class ParentLinkController extends Controller
{
    public function store(Request $request, ParentLinkRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'parent_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
        ]);

        try {
            $repository->link($actor->school_id, $data['parent_id'], $data['student_id'], $actor);
        } catch (InvalidParentLinkTargetFailure $e) {
            return back()->withErrors(['student_id' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Parent linked to student.');
    }

    public function destroy(
        Request $request,
        ScopeService $scope,
        ParentLinkRepository $repository,
        int $parentLink
    ): RedirectResponse {
        $actor = $request->user();

        $link = $scope->tenantScope(StudentParentLink::query(), $actor)->findOrFail($parentLink);

        $repository->unlink($link->id);

        return back()->with('status', 'Parent link removed.');
    }

    /**
     * Design ref: SchoolOS Account Creation & Onboarding plan, §4 (Parent
     * onboarding)
     *
     * approve()/reject() are this pass's addition — the only two actions
     * that ever resolve a self-service ParentLinkRepository::request()
     * row. Both resolve {parentLink} through ScopeService::tenantScope()
     * first, same 404-not-403 discipline as destroy() above, and both
     * carry 'scope.checked' (route-level) for that reason. approve()
     * deliberately calls the existing link() — never duplicates its
     * activation logic — so 'active' is still written in exactly one
     * place in the whole codebase regardless of which flow (admin-direct
     * or self-service-then-approved) reached it.
     */
    public function approve(
        Request $request,
        ScopeService $scope,
        ParentLinkRepository $repository,
        int $parentLink
    ): RedirectResponse {
        $actor = $request->user();

        $link = $scope->tenantScope(StudentParentLink::query(), $actor)->findOrFail($parentLink);

        if ($link->status !== 'pending') {
            return back()->withErrors(['parent_link' => 'This request is no longer pending.']);
        }

        $repository->link($actor->school_id, $link->parent_id, $link->student_id, $actor);

        return back()->with('status', 'Parent link approved.');
    }

    public function reject(
        Request $request,
        ScopeService $scope,
        ParentLinkRepository $repository,
        int $parentLink
    ): RedirectResponse {
        $link = $scope->tenantScope(StudentParentLink::query(), $request->user())->findOrFail($parentLink);

        $repository->reject($link->id);

        return back()->with('status', 'Parent link request rejected.');
    }
}
