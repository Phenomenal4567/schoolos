<?php

namespace App\Exceptions\Communication;

use App\Models\User;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §5, §6, §8
 *
 * Thrown by ContentReadReceiptRepository::markRead() when the caller has
 * no relationshipScope()-visible access to the target entity_type/
 * entity_id — "a receipt is proof the caller saw something they were
 * allowed to see, not a free-standing fact" (18 §6). Same shape as
 * UnauthorizedGradingFailure: one exception type regardless of whether
 * the underlying row doesn't exist, belongs to another school, or exists
 * but isn't relationship-visible to this caller — callers convert this
 * to a 404, never a 403, matching every other scope violation in this
 * bundle.
 */
class UnauthorizedReadReceiptFailure extends \Exception
{
    public function __construct(public readonly User $actor, public readonly string $entityType, public readonly int $entityId)
    {
        parent::__construct(sprintf(
            "User #%d cannot mark %s #%d as read: it does not resolve within this user's "
            . 'tenant and relationship scope.',
            $actor->id,
            $entityType,
            $entityId,
        ));
    }
}
