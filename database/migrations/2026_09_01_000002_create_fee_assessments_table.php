<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 22-schoolos-finance-schema.md §2
// Decision refs: 16-schoolos-decisions-register.md D11, D12
//
// The one row for "what a student owes" for a given fee_category/year.
// amount_due is fixed at INSERT time (D11) and never rewritten once
// payments exist against a row — see FeeAssessmentRepository. There is
// deliberately no amount_remaining column (computed, not stored — see
// that repository's amountRemaining()) and status is a cached
// convenience the same repository keeps in sync, never independently
// settable.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('fee_category_id')->constrained('fee_categories');
            $table->decimal('base_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('scholarship_amount', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2);
            $table->date('due_date')->nullable();
            $table->foreignId('rolled_over_from_assessment_id')
                ->nullable()
                ->constrained('fee_assessments');
            $table->enum('status', ['open', 'partially_paid', 'paid', 'void'])->default('open');
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_assessments');
    }
};
