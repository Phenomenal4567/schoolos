<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 22-schoolos-finance-schema.md §4
// Decision ref: 16-schoolos-decisions-register.md D13
//
// One ledger for both Paystack and manual/receipt-upload payments
// (discovery §10.2/§10.3) — a manual and an online payment differ only
// in processor and which of paystack_reference/receipt_upload_path is
// populated, not in table or write path (PaymentRepository). Only
// status = 'confirmed' rows count toward a fee_assessment's derived
// amount_remaining.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('fee_assessment_id')->constrained('fee_assessments');
            $table->decimal('amount', 12, 2);
            $table->enum('processor', ['paystack', 'manual']);
            $table->string('paystack_reference')->nullable()->unique();
            $table->string('receipt_upload_path')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'failed'])->default('pending');
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'fee_assessment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
