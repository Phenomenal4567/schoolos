<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 12-schoolos-architecture.md §3a, 14-schoolos-implementation-plan.md §1 row 8
 *
 * Pure declarative marker — no runtime behavior. Attaching 'scope.checked'
 * to a route is how that route asserts "the controller action this points
 * to calls ScopeService::tenantScope()/relationshipScope() before
 * resolving its single record." A middleware alone can't verify a
 * controller body calls ScopeService — the actual DB-level constraint
 * still has to happen inside the controller, against the specific query it
 * builds. What this marker gives Phase1TestGateTest's route-table lint
 * (previously markTestSkipped(), now real — see that test) is something
 * concrete to check for at the route-table level: every route shaped like
 * "resolve one record by ID" must carry this middleware, or the lint fails
 * the build. That directly targets F22's failure mode — destroy() had a
 * scope check, show()/update() on the same controller silently didn't, and
 * nothing forced anyone to notice. A route that forgets this middleware
 * now fails a test instead of shipping quietly unscoped.
 */
class MarkScopeChecked
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
