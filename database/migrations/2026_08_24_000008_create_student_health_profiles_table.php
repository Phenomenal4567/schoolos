<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit ref: 13-schoolos-database-schema-v2.md §4
// Direct fix for F14: GegoK12 mixed medical/health fields directly into
// student_academics (the yearly academic enrollment table), giving medical
// data the same access-control surface as roll number/academic status.
// Split into its own table with its own (independently configurable)
// access policy — default: school nurse/admin roles only, not every staff
// member who can view a class roster.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_health_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('student_id')->unique()->constrained('users');
            $table->text('medication_problems')->nullable();
            $table->text('medication_needs')->nullable();
            $table->text('medication_allergies')->nullable();
            $table->text('food_allergies')->nullable();
            $table->text('other_allergies')->nullable();
            $table->text('other_medical_info')->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_health_profiles');
    }
};
