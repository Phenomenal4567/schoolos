<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 22-schoolos-finance-schema.md §5 (discovery §13.2)
//
// Deliberately simple — no discount/scholarship/rollover machinery.
// Admin/accountant-facing only, no parent visibility, no student_id
// column — scoped by ScopeService::tenantScope() only (21 §7).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->enum('category', ['staff_payment', 'operational', 'other']);
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->date('incurred_on');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'incurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
