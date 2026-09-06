<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class ExamGovernanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_exam_component_weights_must_sum_to_100(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/admin/exam-components', [
            'components' => [
                ['name' => 'CA', 'weight_percent' => 40],
                ['name' => 'Exam', 'weight_percent' => 50],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('components');
        $this->assertDatabaseCount('exam_components', 0);
    }

    public function test_teacher_entered_mark_defaults_to_draft_and_is_invisible_to_portals(): void
    {
        [$school, $year, $subject, $teacher, $section, $exam, $component, $student, $parent] = $this->examRoster();

        $this->actingAs($teacher)->post('/teacher/exam-marks', [
            'exam_id' => $exam->id,
            'exam_component_id' => $component->id,
            'student_id' => $student->id,
            'marks_obtained' => 32,
            'max_marks' => 40,
        ])->assertRedirect();

        $this->assertDatabaseHas('exam_marks', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'exam_component_id' => $component->id,
            'status' => 'draft',
        ]);

        $this->actingAs($parent)
            ->get("/parent/exams/{$exam->id}")
            ->assertOk()
            ->assertViewHas('marks', fn ($marks) => $marks->isEmpty());

        $this->actingAs($student)
            ->get("/student/exams/{$exam->id}")
            ->assertOk()
            ->assertViewHas('marks', fn ($marks) => $marks->isEmpty());
    }

    public function test_admin_edit_writes_one_audit_log_with_previous_and_new_values(): void
    {
        [$school, , , , , $exam, $component, $student] = $this->examRoster();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $mark = $this->makeExamMark($school, $exam, $student, $admin, [
            'exam_component_id' => $component->id,
            'marks_obtained' => 20,
            'max_marks' => 40,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->post("/admin/exams/{$exam->id}/marks/{$mark->id}", [
            'marks_obtained' => 30,
            'max_marks' => 40,
        ])->assertRedirect();

        $this->assertDatabaseCount('audit_logs', 1);

        $audit = AuditLog::first();
        $this->assertSame('exam_mark.updated', $audit->action);
        $this->assertSame(['marks_obtained' => '20.00', 'max_marks' => '40.00'], $audit->before_state);
        $this->assertSame(['marks_obtained' => '30.00', 'max_marks' => '40.00'], $audit->after_state);
    }

    public function test_publish_rejects_a_non_reviewed_mark(): void
    {
        [$school, , , , , $exam, $component, $student] = $this->examRoster();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $mark = $this->makeExamMark($school, $exam, $student, $admin, [
            'exam_component_id' => $component->id,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->post("/admin/exams/{$exam->id}/marks/{$mark->id}/publish")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('exam_marks', [
            'id' => $mark->id,
            'status' => 'draft',
        ]);
    }

    public function test_result_pdf_download_requires_a_published_linked_student_result(): void
    {
        [$school, , , , , $exam, $component, $student, $parent] = $this->examRoster();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $mark = $this->makeExamMark($school, $exam, $student, $admin, [
            'exam_component_id' => $component->id,
            'status' => 'reviewed',
        ]);

        $this->actingAs($parent)
            ->get("/parent/exams/{$exam->id}/results/{$student->id}/download")
            ->assertNotFound();

        $this->actingAs($admin)
            ->post("/admin/exams/{$exam->id}/marks/{$mark->id}/publish")
            ->assertRedirect();

        $this->actingAs($parent)
            ->get("/parent/exams/{$exam->id}/results/{$student->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $otherStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $this->actingAs($parent)
            ->get("/parent/exams/{$exam->id}/results/{$otherStudent->id}/download")
            ->assertNotFound();

        $this->actingAs($student)
            ->get("/student/exams/{$exam->id}/result/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_teacher_can_record_a_class_teacher_remark_for_their_own_section(): void
    {
        [$school, , , $teacher, , $exam, , $student] = $this->examRoster();

        $this->actingAs($teacher)->post('/teacher/exam-remarks', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'remark' => 'Shows strong effort this term.',
        ])->assertRedirect();

        $this->assertDatabaseHas('exam_remarks', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'class_teacher_remark' => 'Shows strong effort this term.',
        ]);
    }

    public function test_admin_can_record_a_proprietor_remark_and_it_is_audit_logged(): void
    {
        [$school, , , , , $exam, , $student] = $this->examRoster();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $this->actingAs($admin)->post("/admin/exams/{$exam->id}/remarks/{$student->id}", [
            'remark' => 'A pleasure to have in school.',
        ])->assertRedirect();

        $this->assertDatabaseHas('exam_remarks', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'proprietor_remark' => 'A pleasure to have in school.',
        ]);

        $audit = AuditLog::first();
        $this->assertSame('exam_remark.proprietor_updated', $audit->action);
    }

    public function test_result_pdf_includes_attendance_totals_and_both_remark_types(): void
    {
        [$school, $year, , $teacher, $section, $exam, $component, $student, $parent] = $this->examRoster();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $mark = $this->makeExamMark($school, $exam, $student, $admin, [
            'exam_component_id' => $component->id,
            'status' => 'reviewed',
        ]);
        $this->actingAs($admin)->post("/admin/exams/{$exam->id}/marks/{$mark->id}/publish")->assertRedirect();

        $this->makeAttendanceRecord($school, $year, $section, $student, ['date' => now()->toDateString(), 'status' => 'present']);
        $this->makeExamRemark($school, $exam, $student, [
            'class_teacher_remark' => 'Keep up the good work.',
            'class_teacher_remark_by' => $teacher->id,
            'proprietor_remark' => 'Well done this term.',
            'proprietor_remark_by' => $admin->id,
        ]);

        $content = $this->actingAs($parent)
            ->get("/parent/exams/{$exam->id}/results/{$student->id}/download")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Times school opened: 1', $content);
        $this->assertStringContainsString('Times student attended: 1', $content);
        $this->assertStringContainsString('Class teacher remark: Keep up the good work.', $content);
        $this->assertStringContainsString('Proprietor remark: Well done this term.', $content);
    }

    private function examRoster(): array
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $year, $teacher);
        $exam = $this->makeExam($school, $year, $section, $subject);
        $component = $this->makeExamComponent($school, ['name' => 'CA', 'weight_percent' => 40]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $year, $section, $student);
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $student);

        return [$school, $year, $subject, $teacher, $section, $exam, $component, $student, $parent];
    }
}
