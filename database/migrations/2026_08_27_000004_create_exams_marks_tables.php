<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 17-schoolos-academic-domain-map.md §2, §3, §7
// Decision ref: 16-schoolos-decisions-register.md corrections section
// ("Exams/Timetable: core, not an addon/plugin boundary")
//
// GegoK12's Exam/Marks surface never actually worked either (15 §1 —
// Api\ExamController / Api\MarksController import App\Models\Exam /
// App\Models\Mark, neither of which has ever existed in app/Models),
// so — same as timetable_slots — there's no working reference schema to
// port, only the already-made "core, not addon" decision from 16.
//
// One exam row per class_section per subject, not one row covering
// multiple subjects — resolves 17 §7's open "exam granularity" question.
// This is what lets exams carry class_section_id directly and be covered
// by ScopeService's existing generic dispatch with zero new code (17
// §3), the same way assignments/lesson_plans/timetable_slots are: a
// single exams row that spanned several subjects would need either a
// separate exam_subjects join table or would force exam_marks to carry
// subject_id itself just to know which class_section it's scoped to —
// both more machinery than this phase's actual requirement (scope every
// new resource type correctly) needs. A school's "Midterm" as a named
// event spanning several subjects is a display-grouping concern (a
// shared `name` value across several per-subject exams.rows), not a
// schema concept this phase has a requirement to model.
//
// exam_marks mirrors assignment_submissions' shape exactly, for the same
// reason: carries exam_id + student_id, not class_section_id directly,
// so ScopeService's existing generic student_id dispatch covers the
// parent/student side, and the new teacherRelationshipScope() branch
// (this pass) joins exam_id -> exams.class_section_id for the teacher
// side.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('class_section_id')->constrained('class_sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->string('name'); // 'Midterm', 'Final' — shared across
                                     // the several per-subject rows a
                                     // school groups under one named
                                     // event; a display concept, not a
                                     // schema-level grouping (see comment
                                     // above)
            $table->date('exam_date');
            $table->timestamps();
        });

        Schema::create('exam_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->string('name');
            $table->decimal('weight_percent', 5, 2);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('exam_id')->constrained('exams');
            $table->foreignId('exam_component_id')->constrained('exam_components');
            $table->foreignId('student_id')->constrained('users');
            $table->decimal('marks_obtained', 6, 2);
            $table->decimal('max_marks', 6, 2);
            $table->string('status')->default('draft');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['exam_id', 'student_id', 'exam_component_id'], 'exam_marks_unique_exam_student_component');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_marks');
        Schema::dropIfExists('exam_components');
        Schema::dropIfExists('exams');
    }
};
