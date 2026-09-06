<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: Timetable Management module — see the
 * 2026_09_04_000005_add_can_manage_timetable_to_staff_profiles_table
 * migration's doc comment.
 *
 * Gates the admin.timetable.* route group to two kinds of users:
 * school_admin (owns the module by default, like every other admin.*
 * group — see EnsureUserHasRole's doc comment for that convention), or
 * any other authenticated staff user whose own staff_profiles row has
 * can_manage_timetable = true (the school_admin-granted delegation).
 * This is deliberately a separate middleware from 'role:school_admin'
 * rather than a third argument to EnsureUserHasRole — that middleware's
 * whole contract is "does role->key appear in this fixed list," and
 * delegation is a per-user grant, not a role.
 *
 * Like EnsureUserHasRole, this only answers "can this user reach the
 * route at all." ScopeService::tenantScope() still runs inside the
 * controller to constrain requests to the actor's own school_id — a
 * delegated staff member has no school_id of their own to abuse (they
 * belong to exactly one school, same as any other non-super_admin user),
 * so no separate cross-tenant concern is introduced here.
 */
class EnsureCanManageTimetable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'You do not have access to this area.');
        }

        $roleKey = $user->role->key ?? null;

        if ($roleKey === 'school_admin') {
            return $next($request);
        }

        if ($user->staffProfile?->can_manage_timetable) {
            return $next($request);
        }

        abort(403, 'You do not have access to this area.');
    }
}
