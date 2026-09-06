<?php

namespace App\Repositories;

use App\Events\FeeAssessed;
use App\Exceptions\Fee\InvalidFeeAssessmentTargetFailure;
use App\Models\Discount;
use App\Models\FeeAssessment;
use App\Models\FeeCategory;
use App\Models\Scholarship;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 21-schoolos-finance-architecture.md §2,
 * 22-schoolos-finance-schema.md §2/§3.
 * Decision ref: 16-schoolos-decisions-register.md D11.
 *
 * The one write path for fee_assessments (and, alongside it, the one
 * write path for discounts — see assess()'s doc comment). Mirrors
 * EnrollmentRepository::assertIsStudent()'s gate exactly for
 * student_id, and ClassSectionRepository::create()'s "$schoolId is the
 * caller's own scoped context, never client input" discipline for
 * every tenant-owned FK this class touches.
 *
 * amount_due is computed once, here, at INSERT time (D11) and never
 * rewritten by any other code path — FeeAssessment's own doc comment
 * repeats this. adjust() is D11's escape hatch for a discount/
 * scholarship granted after payments already exist against an
 * assessment: it creates a new, linked fee_assessment carrying only the
 * adjustment delta, rather than mutating the original row's amount_due.
 */
class FeeAssessmentRepository
{
    /**
     * Computes discount_amount from $discount (if given) against
     * $baseAmount, then looks up any applicable Scholarship policy row
     * for ($studentId, $academicYearId, $feeCategoryId) to compute
     * scholarship_amount against the post-discount remainder, then
     * writes the assessment (and, if $discount was given, the linked
     * Discount row) inside one transaction.
     *
     * @param  array{type: string, value: string, reason?: string|null}|null  $discount
     *
     * @throws InvalidFeeAssessmentTargetFailure if the target user's
     *         role does not resolve to 'student'.
     * @throws \InvalidArgumentException if $feeCategoryId doesn't
     *         belong to $schoolId, or if $discount's 'type' isn't one
     *         of individual/percentage/fixed.
     */
    public function assess(
        int $schoolId,
        int $academicYearId,
        int $studentId,
        int $feeCategoryId,
        string $baseAmount,
        User $actor,
        ?array $discount = null,
        ?string $dueDate = null,
    ): FeeAssessment {
        $student = $this->assertIsStudent($studentId);
        $feeCategory = $this->assertFeeCategoryBelongsToSchool($feeCategoryId, $schoolId);

        $discountAmount = $discount !== null
            ? $this->computeDiscountAmount($baseAmount, $discount['type'], $discount['value'])
            : '0.00';

        $remainingAfterDiscount = bcsub($baseAmount, $discountAmount, 2);

        $scholarship = $this->applicableScholarship($schoolId, $student->id, $academicYearId, $feeCategory->id);
        $scholarshipAmount = $this->scholarshipAmountFor($scholarship, $feeCategory->id, $remainingAfterDiscount);

        $amountDue = bcsub($remainingAfterDiscount, $scholarshipAmount, 2);

        $assessment = DB::transaction(function () use (
            $schoolId,
            $academicYearId,
            $student,
            $feeCategory,
            $baseAmount,
            $discountAmount,
            $scholarshipAmount,
            $amountDue,
            $dueDate,
            $discount,
            $actor,
        ) {
            $assessment = FeeAssessment::create([
                'school_id' => $schoolId,
                'academic_year_id' => $academicYearId,
                'student_id' => $student->id,
                'fee_category_id' => $feeCategory->id,
                'base_amount' => $baseAmount,
                'discount_amount' => $discountAmount,
                'scholarship_amount' => $scholarshipAmount,
                'amount_due' => $amountDue,
                'due_date' => $dueDate,
                'status' => 'open',
            ]);

            if ($discount !== null) {
                Discount::create([
                    'school_id' => $schoolId,
                    'fee_assessment_id' => $assessment->id,
                    'type' => $discount['type'],
                    'value' => $discount['value'],
                    'granted_by' => $actor->id,
                    'reason' => $discount['reason'] ?? null,
                ]);
            }

            return $assessment;
        });

        // A scholarship/discount that fully covers the fee leaves nothing
        // for a parent to be alerted about — see FeeAssessed's own doc
        // comment.
        if (bccomp((string) $assessment->amount_due, '0', 2) > 0) {
            event(new FeeAssessed($assessment));
        }

        return $assessment;
    }

    /**
     * D11's escape hatch: a discount granted after $existingAssessmentId
     * already has payments recorded against it must not rewrite that
     * row's amount_due (a parent may have already paid against the
     * original total). Instead, creates a new fee_assessment for the
     * same student/year/category, carrying only the adjustment as a
     * negative base_amount/amount_due line item — a visible credit, not
     * a silent change to a number already paid against.
     *
     * The discount percentage/fixed amount is computed against the
     * *original* assessment's base_amount, matching how the initial
     * discount in assess() is always computed against base_amount, not
     * against an already-reduced figure.
     *
     * @param  array{type: string, value: string, reason?: string|null}  $discount
     */
    public function adjust(int $existingAssessmentId, User $actor, array $discount): FeeAssessment
    {
        $existing = FeeAssessment::findOrFail($existingAssessmentId);

        $discountAmount = $this->computeDiscountAmount($existing->base_amount, $discount['type'], $discount['value']);
        $creditAmount = bcmul($discountAmount, '-1', 2);

        return DB::transaction(function () use ($existing, $actor, $discount, $creditAmount) {
            $adjustment = FeeAssessment::create([
                'school_id' => $existing->school_id,
                'academic_year_id' => $existing->academic_year_id,
                'student_id' => $existing->student_id,
                'fee_category_id' => $existing->fee_category_id,
                'base_amount' => $creditAmount,
                'discount_amount' => 0,
                'scholarship_amount' => 0,
                'amount_due' => $creditAmount,
                'status' => 'open',
            ]);

            Discount::create([
                'school_id' => $existing->school_id,
                'fee_assessment_id' => $adjustment->id,
                'type' => $discount['type'],
                'value' => $discount['value'],
                'granted_by' => $actor->id,
                'reason' => $discount['reason'] ?? null,
            ]);

            return $adjustment;
        });
    }

    private function computeDiscountAmount(string $baseAmount, string $type, string $value): string
    {
        if (! in_array($type, ['individual', 'percentage', 'fixed'], true)) {
            throw new \InvalidArgumentException(
                "FeeAssessmentRepository: invalid discount type '{$type}'."
            );
        }

        $rawAmount = $type === 'percentage'
            ? bcdiv(bcmul($baseAmount, $value, 4), '100', 2)
            : $value;

        // A discount can never exceed the amount it's discounting from —
        // capped rather than allowed to push amount_due negative.
        return bccomp($rawAmount, $baseAmount, 2) > 0 ? $baseAmount : $rawAmount;
    }

    /**
     * The most relevant Scholarship policy row for this student/year:
     * full/partial apply to every assessment for the year;
     * specific_exemption only applies when $feeCategoryId matches.
     */
    private function applicableScholarship(int $schoolId, int $studentId, int $academicYearId, int $feeCategoryId): ?Scholarship
    {
        return Scholarship::where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where(function ($query) use ($feeCategoryId) {
                $query->whereIn('type', ['full', 'partial'])
                    ->orWhere(function ($inner) use ($feeCategoryId) {
                        $inner->where('type', 'specific_exemption')
                            ->where('fee_category_id', $feeCategoryId);
                    });
            })
            ->latest('id')
            ->first();
    }

    /**
     * See this class's own doc comment for the value-interpretation
     * decision this method encodes: 'partial' is always a percentage of
     * $remaining, never a fixed amount, since the schema gives no
     * `value_type` column to disambiguate the two.
     */
    private function scholarshipAmountFor(?Scholarship $scholarship, int $feeCategoryId, string $remaining): string
    {
        if ($scholarship === null) {
            return '0.00';
        }

        return match ($scholarship->type) {
            'full' => $remaining,
            'partial' => bcdiv(bcmul($remaining, (string) $scholarship->value, 4), '100', 2),
            'specific_exemption' => $scholarship->fee_category_id === $feeCategoryId ? $remaining : '0.00',
            default => '0.00',
        };
    }

    private function assertIsStudent(int $studentId): User
    {
        $targetUser = User::with('role')->findOrFail($studentId);

        if (($targetUser->role->key ?? null) !== 'student') {
            throw new InvalidFeeAssessmentTargetFailure($targetUser);
        }

        return $targetUser;
    }

    private function assertFeeCategoryBelongsToSchool(int $feeCategoryId, int $schoolId): FeeCategory
    {
        $category = FeeCategory::findOrFail($feeCategoryId);

        if ($category->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "FeeAssessmentRepository::assess(): fee_category #{$feeCategoryId} belongs to school "
                . "#{$category->school_id}, not the requested school #{$schoolId}."
            );
        }

        return $category;
    }
}
