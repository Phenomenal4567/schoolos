<?php

namespace App\Repositories;

use App\Exceptions\Communication\UnauthorizedFeedbackFailure;
use App\Models\Feedback;
use App\Models\StudentParentLink;
use App\Models\User;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §3, §6, §8 (F27)
 *
 * The one write path for Feedback — no controller writes a Feedback row
 * directly, matching this bundle's "one repository method owns a
 * resource's creation" shape (AnnouncementRepository::create(), etc.).
 *
 * The ownership check closes F27's store() spoofing gap: a parent may
 * only file feedback under an actively-linked child, a student only
 * under their own id — checked before any write, same "reject loudly
 * before any transaction opens" posture AnnouncementRepository::create()
 * already uses. No teacher/admin authoring path exists yet — 18 §4/§8
 * scopes Feedback authorship to parent/student only for this pass.
 */
class FeedbackRepository
{
    /**
     * @throws UnauthorizedFeedbackFailure if $actor may not file feedback
     *         under $studentId (see this class's doc comment).
     * @throws \InvalidArgumentException if $actor's role may not author
     *         feedback at all, if $recipientType is invalid, if
     *         $recipientType is 'teacher' but $recipientTeacherId is
     *         missing or doesn't resolve to a teacher in $schoolId, or if
     *         $recipientType is 'school' but $recipientTeacherId is set.
     */
    public function create(
        int $schoolId,
        User $actor,
        int $studentId,
        string $message,
        ?string $category,
        string $recipientType,
        ?int $recipientTeacherId,
    ): Feedback {
        $roleKey = $actor->role->key ?? null;

        if (! in_array($roleKey, ['parent', 'student'], true)) {
            throw new \InvalidArgumentException(
                "FeedbackRepository::create(): role '{$roleKey}' may not author feedback."
            );
        }

        $this->assertOwnsStudent($actor, $roleKey, $studentId);

        if (! in_array($recipientType, ['school', 'teacher'], true)) {
            throw new \InvalidArgumentException(
                "FeedbackRepository::create(): invalid recipient_type '{$recipientType}'."
            );
        }

        if ($recipientType === 'teacher') {
            if ($recipientTeacherId === null) {
                throw new \InvalidArgumentException(
                    "FeedbackRepository::create(): recipient_type 'teacher' requires a recipient_teacher_id."
                );
            }

            $recipientTeacher = User::where('id', $recipientTeacherId)
                ->where('school_id', $schoolId)
                ->whereHas('role', fn ($q) => $q->where('key', 'teacher'))
                ->first();

            if ($recipientTeacher === null) {
                throw new \InvalidArgumentException(
                    "FeedbackRepository::create(): recipient_teacher_id #{$recipientTeacherId} does not "
                    . "resolve to a teacher in school #{$schoolId}."
                );
            }
        } elseif ($recipientTeacherId !== null) {
            throw new \InvalidArgumentException(
                "FeedbackRepository::create(): recipient_type 'school' must not carry a recipient_teacher_id."
            );
        }

        return Feedback::create([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'author_id' => $actor->id,
            'category' => $category,
            'message' => $message,
            'recipient_type' => $recipientType,
            'recipient_teacher_id' => $recipientTeacherId,
        ]);
    }

    /**
     * @throws UnauthorizedFeedbackFailure
     */
    private function assertOwnsStudent(User $actor, string $roleKey, int $studentId): void
    {
        if ($roleKey === 'student') {
            if ($actor->id !== $studentId) {
                throw new UnauthorizedFeedbackFailure($actor, $studentId);
            }

            return;
        }

        $isLinked = StudentParentLink::where('parent_id', $actor->id)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();

        if (! $isLinked) {
            throw new UnauthorizedFeedbackFailure($actor, $studentId);
        }
    }
}
