<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 22-schoolos-finance-schema.md §3
//
// discounts is assessment-scoped (discovery §10.4 — always applied "to
// a fee_assessment"): a policy record explaining why a given
// assessment's discount_amount is what it is. scholarships is
// enrollment/session-scoped (discovery §10.5 — applied "at the student-
// enrollment level"): a policy that FeeAssessmentRepository::assess()
// looks up per student/academic_year (and, for specific_exemption, per
// fee_category) to compute a *new* assessment's scholarship_amount
// snapshot. The policy lives here; the fact lives on fee_assessments.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('fee_assessment_id')->constrained('fee_assessments');
            $table->enum('type', ['individual', 'percentage', 'fixed']);
            $table->decimal('value', 12, 2);
            $table->foreignId('granted_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->enum('type', ['full', 'partial', 'specific_exemption']);
            $table->decimal('value', 12, 2)->nullable();
            $table->foreignId('fee_category_id')->nullable()->constrained('fee_categories');
            $table->foreignId('granted_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['school_id', 'student_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarships');
        Schema::dropIfExists('discounts');
    }
};
