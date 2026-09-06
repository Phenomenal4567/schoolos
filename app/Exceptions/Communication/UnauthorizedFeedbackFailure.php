<?php

namespace App\Exceptions\Communication;

use App\Models\User;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §6, §8 (F27)
 *
 * Thrown by FeedbackRepository::create() when $studentId isn't the
 * acting parent's own linked child, or isn't the acting student's own
 * id — the exact spoofing gap F27 found in the reference codebase's
 * FeedbackController::store() ("an unchecked student_id on write letting
 * a parent file feedback under a child that isn't theirs"). Same
 * "reject loudly before any transaction opens" posture
 * AnnouncementRepository::create() already uses, converted to a 404 by
 * the calling controller, never a 403, matching every other scope
 * violation in this bundle.
 */
class UnauthorizedFeedbackFailure extends \Exception
{
    public function __construct(public readonly User $actor, public readonly int $studentId)
    {
        parent::__construct(sprintf(
            "User #%d cannot file feedback under student #%d: not their own id, and not an "
            . 'actively linked child.',
            $actor->id,
            $studentId,
        ));
    }
}
