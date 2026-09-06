<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 19-discovery-hierarchy-gap-closure-plan.md §10, discovery
// doc §12 (11-step admission/enrollment workflow).
// Decision ref: 16-schoolos-decisions-register.md D16 (admission-time
// fee acknowledgment ties to the real fee_categories table, not a
// placeholder checkbox — 6d closed before this track started, so the
// "ship as checkbox pending real fee integration" fallback 19 §10
// offered is no longer the cheaper path).
//
// A prospective applicant is not a users row yet — there is no
// authenticated actor to attach this to at submission time (submit() is
// the one genuinely unauthenticated write path in this schema) — so
// applicant_data is structured JSON (name/DOB/parent-guardian info/
// medical info), not a foreign key. resulting_user_id/
// resulting_student_enrollment_id are the two columns that get filled
// in only on accept(), the point at which "prospective applicant"
// becomes "real User" — see AdmissionApplicationRepository::accept()'s
// own doc comment for that conversion.
//
// fee_category_acknowledgments is a JSON array of fee_categories.id
// values, validated at submit() time against the submitting school's
// own categories (never trusted as given) — see
// AdmissionApplicationRepository::submit()'s own doc comment.
//
// status has no schema-level state machine (Laravel migrations can't
// express "reject/withdraw require decision_reason" or "accept only
// from submitted/under_review" as a CHECK across columns) — those
// transitions are enforced in AdmissionApplicationRepository, the same
// posture PromotionRepository::override() takes for
// MissingPromotionReasonFailure.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->enum('status', ['submitted', 'under_review', 'accepted', 'rejected', 'withdrawn'])
                ->default('submitted');
            $table->json('applicant_data');
            $table->json('fee_category_acknowledgments');
            $table->json('documents')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('resulting_user_id')->nullable()->constrained('users');
            $table->foreignId('resulting_student_enrollment_id')
                ->nullable()
                ->constrained('student_enrollments');
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
    }
};
