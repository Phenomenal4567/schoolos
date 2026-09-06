<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 14-schoolos-implementation-plan.md §2 (parent web dashboard)
 *
 * Gates a route group to one or more role keys (e.g. `role:parent`),
 * checked against the authenticated user's Role::$key — the same
 * code-facing value ScopeService::relationshipScope() matches against, so
 * "which role can reach this route" and "which role gets which
 * relationshipScope variant" are read from the same column, never two
 * definitions that could drift apart.
 *
 * This middleware answers "can this role reach this route at all" — it is
 * not a substitute for ScopeService::tenantScope()/relationshipScope(),
 * which still runs inside the controller to constrain *which records*
 * within that role's reach a given request may touch. A parent reaching
 * /parent/children/{student} has already passed this check by the time the
 * controller runs; ScopeService is what stops them reading a child that
 * isn't theirs.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->key ?? null, $roles, true)) {
            abort(403, 'You do not have access to this area.');
        }

        return $next($request);
    }
}
