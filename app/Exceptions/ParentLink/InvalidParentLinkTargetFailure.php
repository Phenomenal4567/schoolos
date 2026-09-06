<?php

namespace App\Exceptions\ParentLink;

use App\Models\User;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 16-schoolos-decisions-register.md D3 (same shape of gap,
 * applied to student_parent_links)
 *
 * Thrown by ParentLinkRepository::link() when either side of the link
 * doesn't hold the expected role — student_parent_links.parent_id and
 * .student_id are both plain FKs to users, so nothing at the schema
 * level stops a link between two users of the wrong roles. One exception
 * type covers both sides ($expectedRole distinguishes them in the
 * message) rather than two separate classes, since both checks live in
 * the same private gate (assertHasRole(), private to
 * ParentLinkRepository) and a caller only ever needs to know "which
 * user, expected to be what."
 */
class InvalidParentLinkTargetFailure extends \Exception
{
    public function __construct(public readonly User $targetUser, public readonly string $expectedRole)
    {
        parent::__construct(sprintf(
            "User #%d cannot be linked as a %s: role is '%s', not '%s'.",
            $targetUser->id,
            $expectedRole,
            $targetUser->role->key ?? 'unknown',
            $expectedRole,
        ));
    }
}
