<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\AuthFailure;
use App\Http\Controllers\Controller;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Design ref: 12-schoolos-architecture.md §2, 14-schoolos-implementation-plan.md §0
 *
 * The one web session controller for every role. Per Ground Rule 0 ("no
 * duplicated business logic between web and mobile/API — one
 * AuthenticationService, called from both"), there is deliberately no
 * per-role login controller here — no ParentLoginController, no
 * TeacherLoginController. That pattern (several call sites each deciding
 * independently how to authenticate) is exactly what F5/F6 trace back to
 * in GegoK12. Every web role, parent included, logs in through this one
 * controller; AuthenticationService::dashboardPathFor()'s role dispatch
 * is presentation-layer routing after the fact, not a second
 * authentication path. That method used to live here as a private
 * method; it moved onto AuthenticationService (SchoolOS Account Creation
 * & Onboarding plan) once a second caller — InvitationRepository::accept()'s
 * "set password, land in the right portal" flow — needed the identical
 * dispatch without duplicating it.
 */
class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuthenticationService $authService): RedirectResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        try {
            $resolved = $authService->resolveIdentifier($credentials['identifier']);
            $user = $authService->authenticate($resolved, $credentials['password']);
        } catch (AuthFailure $e) {
            // AuthFailure and its subtypes (UserNotFoundFailure,
            // SchoolSuspendedFailure) all carry a message safe to show
            // directly — see each exception's own doc comment. No
            // generic "invalid credentials" re-wrap needed here; that
            // would undo D2's whole point of making SchoolSuspendedFailure
            // distinguishable from a wrong password.
            return back()
                ->withErrors(['identifier' => $e->getMessage()])
                ->onlyInput('identifier');
        }

        // issueSession() is the one place Auth::login() happens for a web
        // request (see its doc comment) — not called again here, so
        // there's exactly one line in the whole codebase that attaches a
        // user to the session.
        $authService->issueSession($user, (bool) ($credentials['remember'] ?? false));

        return redirect()->intended($authService->dashboardPathFor($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
