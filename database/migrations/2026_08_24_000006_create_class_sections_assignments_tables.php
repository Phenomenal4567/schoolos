<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit ref: 13-schoolos-database-schema-v2.md §3
// Decision ref: D3 — class_teacher_id's "must be a teacher" invariant is
// enforced by ClassSectionRepository::assignClassTeacher(), the only
// permitted write path for this column (never a DB trigger). See
// app/Repositories/ClassSectionRepository.php.
//
// class_teacher_assignments is the schema table that F18 (the audit's
// single most significant authorization finding) traces directly back to:
// GegoK12 had this exact data (as class_teacher_links / Teacherlink) but
// its Gate checks never queried it. ScopeService::relationshipScope() for
// teachers MUST join against this table — see app/Services/ScopeService.php.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('standard_id')->constrained('standards');
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('class_teacher_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('class_teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('class_section_id')->constrained('class_sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('teacher_id')->constrained('users');
            $table->timestamps();

            $table->unique(
                ['class_section_id', 'subject_id', 'teacher_id', 'academic_year_id'],
                'class_teacher_assignments_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_teacher_assignments');
        Schema::dropIfExists('class_sections');
    }
};
