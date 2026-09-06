<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit ref: 13-schoolos-database-schema-v2.md §3
// student_parent_links is the table F20's fix (and every generalized
// F20-shaped finding, F21/F23-F29) traces back to — ScopeService::
// relationshipScope() for parents/students MUST intersect against this
// table before returning any student-owned record. Deliberately no
// second, competing mechanism (GegoK12's users.ref_id, flagged as vestigial
// and asymmetrically-buggy in 08-parent-domain-map.md §3, has no schema
// equivalent here at all — see audit §6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('class_section_id')->constrained('class_sections');
            $table->string('roll_number');
            $table->enum('status', ['active', 'transferred', 'graduated', 'withdrawn'])
                  ->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
        });

        Schema::create('student_parent_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('parent_id')->constrained('users');
            $table->foreignId('student_id')->constrained('users');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['parent_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_parent_links');
        Schema::dropIfExists('student_enrollments');
    }
};
