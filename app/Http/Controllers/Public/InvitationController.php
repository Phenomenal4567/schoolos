<?php

namespace App\Http\Controllers\Public;

use App\Exceptions\Invitation\ExpiredInvitationFailure;
use App\Exceptions\Invitation\InvalidInvitationTokenFailure;
use App\Http\Controllers\Controller;
use App\Repositories\InvitationRepository;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
 * Invitation mechanism"
 *
 * Public, unauthenticated invitation-acceptance page — mirrors
 * Public\AdmissionApplicationController exactly: 'guest' middleware, the
 * token carried as a `?token=` query string rather than a route
 * parameter. Same reasoning as that controller's own doc comment on its
 * `?school=` parameter: this genuinely has no authenticated actor to run
 * ScopeService::tenantScope()/relationshipScope() against, so it is
 * honestly exempt from Phase1TestGateTest's row 8 scope.checked lint
 * (that lint only fires on a URI *route parameter*, and a query string
 * isn't one) rather than a workaround.
 *
 * create() does not itself validate the token — InvitationRepository::
 * accept() is the single source of truth for what makes a token valid,
 * so there's no separate "peek" check here that could drift from it. A
 * bad/expired/reused token only surfaces as a form error from store(),
 * after the person has already typed a password once, same as any other
 * form-validation-at-submit-time flow in this app.
 */
class InvitationController extends Controller
{
    public function create(Request $request): View
    {
        $token = $request->query('token');

        return view('public.invitations.accept', [
            'token' => is_string($token) ? $token : '',
        ]);
    }

    public function store(Request $request, InvitationRepository $repository, AuthenticationService $authService): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $user = $repository->accept($data['token'], $data['password']);
        } catch (InvalidInvitationTokenFailure|ExpiredInvitationFailure $e) {
            return back()->withErrors(['token' => $e->getMessage()])->withInput();
        }

        // Same one-line-in-the-whole-codebase discipline
        // SessionController::store() documents — issueSession() is what
        // actually attaches $user to the session; accepting an invitation
        // lands the person straight in their portal rather than making
        // them immediately re-type the password they just chose.
        $authService->issueSession($user);

        return redirect($authService->dashboardPathFor($user))
            ->with('status', 'Your account is active.');
    }
}
