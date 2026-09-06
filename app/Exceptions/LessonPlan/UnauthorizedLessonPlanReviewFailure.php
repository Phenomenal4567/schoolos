<?php

namespace App\Exceptions\LessonPlan;

use App\Models\User;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §4, §5
 *
 * Thrown by LessonPlanRepository::approve()/reject() for either of the
 * two checks F30/F31 found both missing in the reference codebase: the
 * acting user's role isn't school_admin, OR the target lesson plan
 * doesn't resolve within the actor's own tenant scope. Deliberately one
 * exception type for both cases, not two — a caller shouldn't be able to
 * distinguish "wrong role" from "exists, but in another school" from the
 * exception alone, the same "don't reveal whether a cross-tenant row
 * exists" posture the rest of this bundle's scope checks use (e.g.
 * ScopeService's relationship-scoped queries return empty rather than a
 * distinguishable "found but forbidden" result).
 */
class UnauthorizedLessonPlanReviewFailure extends \Exception
{
    public function __construct(public readonly User $actor, public readonly int $lessonPlanId)
    {
        parent::__construct(sprintf(
            "User #%d cannot approve/reject lesson plan #%d: role is '%s', or the lesson plan "
            . 'does not belong to this user\'s school.',
            $actor->id,
            $lessonPlanId,
            $actor->role->key ?? 'unknown',
        ));
    }
}
