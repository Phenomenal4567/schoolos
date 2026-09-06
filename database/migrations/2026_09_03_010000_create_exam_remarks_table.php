<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 26-discovery-hierarchy-status.md ("Rich Report Card Fields").
//
// One row per (exam, student) — a peer table to exam_marks, not a
// column on it: a remark has no weight_percent/marks_obtained or
// draft/reviewed/published governance, and a student can carry zero,
// one, or both remark types at any time (they're independent of mark
// entry). class_teacher_remark is written by the exam's class_section's
// own teacher; proprietor_remark by that school's school_admin — this
// schema has no distinct "proprietor" role, so the one school-level role
// fills that function, same reasoning already applied wherever
// "the school admin acts as the school's authority" comes up elsewhere.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('exam_id')->constrained('exams');
            $table->foreignId('student_id')->constrained('users');
            $table->text('class_teacher_remark')->nullable();
            $table->foreignId('class_teacher_remark_by')->nullable()->constrained('users');
            $table->text('proprietor_remark')->nullable();
            $table->foreignId('proprietor_remark_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_remarks');
    }
};
