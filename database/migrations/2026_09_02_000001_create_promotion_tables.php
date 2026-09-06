<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 19-discovery-hierarchy-gap-closure-plan.md §8,
// 20-phase-6-8-execution-prompt.md §6 (Track 7b)
// Decision ref: 16-schoolos-decisions-register.md D15 (single
// aggregate-threshold rule is sufficient for v1 — no richer rule
// grammar yet)
//
// promotion_rules.criteria is JSON, not a flat min_aggregate column,
// even though D15 confirms a single threshold is all v1 needs — this
// keeps the door open for the richer grammar (per-subject thresholds,
// attendance-based eligibility) 19 §8 raised as a real possibility
// without a second migration if/when that's ever decided. v1's only
// recognized key is 'min_aggregate' (a percentage, 0-100) — enforced
// by PromotionRuleRepository, not by a schema constraint, since JSON
// columns can't carry a CHECK on key presence.
//
// UNIQUE(school_id, academic_year_id, standard_id): one rule per
// standard per year, matching 19 §8's schema sketch ("rule can vary
// per class/standard"). A second setRule() call for the same triple
// is an update, not a new row — PromotionRuleRepository upserts against
// this constraint rather than pre-checking then inserting, the same
// TOCTOU-safe posture EnrollmentRepository::enroll() and
// AttendanceRepository::mark() already use for their own UNIQUE
// constraints.
//
// promotions carries student_id directly, denormalized from
// student_enrollment_id, for the same reason ExamMark and
// AssignmentSubmission do (see either model's doc comment): it lets
// ScopeService's existing generic student_id dispatch cover the
// parent/student read side for free — no new ScopeService branch for
// this resource. student_enrollment_id is still the FK of record (19
// §8's schema sketch) and is what a promotion decision is actually
// about; student_id is a read-side convenience column only, never
// written to independently of it.
//
// UNIQUE(student_enrollment_id): one promotion decision per enrollment
// — a student_enrollment_id is already year-scoped (student_enrollments'
// own UNIQUE(student_id, academic_year_id), 13 §3), so this means "one
// promotion decision per student per year," matching discovery §9's
// framing of promotion as a single end-of-year event, not a log of
// repeated attempts. PromotionRepository upserts against this
// constraint: an automatic re-run before finalization overwrites a
// prior *automatic* decision for the same enrollment, but never
// overwrites a *manual* one (enforced in the repository, not the
// schema — see PromotionRepository::runAutomatic()'s doc comment).
//
// method has no default — every row must state automatic or manual
// explicitly at write time (19 §8's "a promotion never happens without
// one of the two paths being explicit in the record" requirement is
// enforced by PromotionRepository's two separate write methods, one
// per value, never a shared writer that could leave this ambiguous).
// reason is nullable at the schema level (automatic rows never set it)
// but PromotionRepository rejects a manual write with an empty reason
// before it reaches this table — the schema can't express "required
// only when method = manual" itself.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('standard_id')->constrained('standards');
            $table->json('criteria');
            $table->timestamps();

            $table->unique(
                ['school_id', 'academic_year_id', 'standard_id'],
                'promotion_rules_unique_school_year_standard'
            );
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments');
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('from_class_section_id')->constrained('class_sections');
            $table->foreignId('to_class_section_id')->nullable()->constrained('class_sections');
            $table->enum('method', ['automatic', 'manual']);
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique('student_enrollment_id', 'promotions_unique_student_enrollment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('promotion_rules');
    }
};