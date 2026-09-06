<?php

namespace App\Repositories;

use App\Exceptions\Communication\UnauthorizedReadReceiptFailure;
use App\Models\Assignment;
use App\Models\ContentReadReceipt;
use App\Models\SchemeOfWork;
use App\Models\User;
use App\Services\ScopeService;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §6, §8
 *
 * The write path the content_read_receipts migration deliberately
 * deferred (see that migration's own doc comment) — "the caller must
 * already have relationshipScope()-visible access to the underlying
 * entity_type/entity_id before a read-receipt for it can be recorded, a
 * receipt is proof the caller saw something they were allowed to see,
 * not a free-standing fact" (18 §6).
 *
 * $ENTITY_MODELS maps the schema's entity_type enum to the model each
 * type's visibility is checked against. Only 'announcement', 'assignment',
 * 'scheme_of_work', and 'learning_material' are wired here — 'image',
 * 'video', and 'homework' are reserved enum values (13 §6) for content
 * types that don't have their own resource/controller in this codebase
 * yet; marking one as read before the resource it describes exists
 * would have nothing real to check visibility against, so those types
 * are rejected rather than silently accepted. 'scheme_of_work' takes
 * the plain genericEntityVisible() path — its class_section_id column
 * is NOT NULL (19-discovery-hierarchy-gap-closure-plan.md §6), so
 * ScopeService::relationshipScope()'s generic dispatch covers it
 * exactly the way it covers 'assignment', no special case needed.
 * 'announcement' and 'learning_material' are both checked through
 * their own repository's findVisibleTo() rather than a plain
 * ScopeService call, because both models have a nullable
 * class_section_id and a two-branch (school-wide bypass +
 * class_section relationship narrowing) visibility rule their
 * repository already owns — duplicating that logic here would be a
 * second copy of AnnouncementRepository::visibleTo()'s /
 * LearningMaterialRepository::visibleTo()'s own reasoning for why a
 * plain relationshipScope() call isn't sufficient for either model.
 */
class ContentReadReceiptRepository
{
    private const ENTITY_MODELS = [
        'assignment' => Assignment::class,
        'scheme_of_work' => SchemeOfWork::class,
    ];

    /**
     * @throws UnauthorizedReadReceiptFailure if $entityId does not
     *         resolve within $actor's own tenant/relationship scope for
     *         $entityType.
     * @throws \InvalidArgumentException if $entityType has no visibility
     *         check wired (see this class's doc comment).
     */
    public function markRead(
        User $actor,
        string $entityType,
        int $entityId,
        ScopeService $scope,
        AnnouncementRepository $announcements,
        ?LearningMaterialRepository $learningMaterials = null,
    ): ContentReadReceipt {
        $visible = match ($entityType) {
            'announcement' => $announcements->findVisibleTo($entityId, $actor, $scope) !== null,
            'learning_material' => ($learningMaterials ?? new LearningMaterialRepository())
                ->findVisibleTo($entityId, $actor, $scope) !== null,
            default => $this->genericEntityVisible($entityType, $entityId, $actor, $scope),
        };

        if (! $visible) {
            throw new UnauthorizedReadReceiptFailure($actor, $entityType, $entityId);
        }

        return ContentReadReceipt::updateOrCreate(
            [
                'user_id' => $actor->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'school_id' => $actor->school_id,
                'read_at' => now(),
            ],
        );
    }

    private function genericEntityVisible(string $entityType, int $entityId, User $actor, ScopeService $scope): bool
    {
        $modelClass = self::ENTITY_MODELS[$entityType]
            ?? throw new \InvalidArgumentException(
                "ContentReadReceiptRepository::markRead(): no visibility check wired for entity_type "
                . "'{$entityType}'."
            );

        return $scope
            ->relationshipScope($scope->tenantScope($modelClass::query(), $actor), $actor, $modelClass)
            ->where('id', $entityId)
            ->exists();
    }
}
