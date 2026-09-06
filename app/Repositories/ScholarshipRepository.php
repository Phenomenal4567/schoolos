<?php

namespace App\Repositories;

use App\Exceptions\Fee\InvalidScholarshipTargetFailure;
use App\Models\FeeCategory;
use App\Models\Scholarship;
use App\Models\User;

/**
 * Design ref: 22-schoolos-finance-schema.md §3 (discovery §10.5)
 * Decision ref: 16-schoolos-decisions-register.md D11
 *
 * The write path for scholarships — an enrollment/session-scoped policy
 * ("this student has a scholarship for this academic year"), independent
 * of any specific fee_assessment. FeeAssessmentRepository::assess() reads
 * this table at assessment time to compute a new assessment's
 * scholarship_amount snapshot; this repository never touches
 * fee_assessments itself, the same separation of policy-vs-fact §3 draws.
 *
 * grant()'s one gate mirrors EnrollmentRepository::assertIsStudent()'s
 * shape exactly: verify the target user's role resolves to 'student'
 * before writing, same InvalidScholarshipTargetFailure/
 * InvalidFeeAssessmentTargetFailure pairing those two exceptions'
 * doc comments already describe.
 *
 * Value-interpretation note (a build-time decision this schema pass left
 * open, per 22 §3's own "percentage or fixed amount for 'partial'"
 * comment, without a `value_type` column to disambiguate the two): this
 * repository treats every 'partial' value as a percentage (0-100) of the
 * assessment's post-discount remaining balance, computed at assess()
 * time — not a fixed currency amount. Recording that decision here
 * plainly, rather than silently picking one, since the schema itself
 * doesn't settle it. 'full' and 'specific_exemption' both ignore value
 * entirely (full always zeroes the remaining balance; specific_exemption
 * always zeroes the one matching fee_category's remaining balance) — see
 * FeeAssessmentRepository::scholarshipAmountFor().
 */
class ScholarshipRepository
{
    /**
     * @throws InvalidScholarshipTargetFailure if the target user's role
     *         does not resolve to 'student'.
     * @throws \InvalidArgumentException if $type isn't one of
     *         full/partial/specific_exemption, if $type is 'partial'
     *         without a $value, if $type is 'specific_exemption' without
     *         a $feeCategoryId, or if $feeCategoryId doesn't belong to
     *         $schoolId.
     */
    public function grant(
        int $schoolId,
        int $studentId,
        int $academicYearId,
        string $type,
        ?string $value,
        ?int $feeCategoryId,
        User $grantedBy,
        ?string $reason = null,
    ): Scholarship {
        $student = $this->assertIsStudent($studentId);
        $this->assertValidShape($schoolId, $type, $value, $feeCategoryId);

        return Scholarship::create([
            'school_id' => $schoolId,
            'student_id' => $student->id,
            'academic_year_id' => $academicYearId,
            'type' => $type,
            'value' => $type === 'partial' ? $value : null,
            'fee_category_id' => $type === 'specific_exemption' ? $feeCategoryId : null,
            'granted_by' => $grantedBy->id,
            'reason' => $reason,
        ]);
    }

    private function assertIsStudent(int $studentId): User
    {
        $targetUser = User::with('role')->findOrFail($studentId);

        if (($targetUser->role->key ?? null) !== 'student') {
            throw new InvalidScholarshipTargetFailure($targetUser);
        }

        return $targetUser;
    }

    private function assertValidShape(int $schoolId, string $type, ?string $value, ?int $feeCategoryId): void
    {
        if (! in_array($type, ['full', 'partial', 'specific_exemption'], true)) {
            throw new \InvalidArgumentException(
                "ScholarshipRepository::grant(): invalid type '{$type}'."
            );
        }

        if ($type === 'partial' && $value === null) {
            throw new \InvalidArgumentException(
                "ScholarshipRepository::grant(): type 'partial' requires a value."
            );
        }

        if ($type === 'specific_exemption') {
            if ($feeCategoryId === null) {
                throw new \InvalidArgumentException(
                    "ScholarshipRepository::grant(): type 'specific_exemption' requires a fee_category_id."
                );
            }

            $category = FeeCategory::findOrFail($feeCategoryId);

            if ($category->school_id !== $schoolId) {
                throw new \InvalidArgumentException(
                    "ScholarshipRepository::grant(): fee_category #{$feeCategoryId} belongs to school "
                    . "#{$category->school_id}, not the requested school #{$schoolId}."
                );
            }
        }
    }
}
