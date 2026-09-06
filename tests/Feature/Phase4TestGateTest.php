<?php

namespace Tests\Feature;

use App\Exceptions\LessonPlan\UnauthorizedLessonPlanReviewFailure;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\LessonPlan;
use App\Models\StudentEnrollment;
use App\Models\TimetableSlot;
use App\Repositories\ClassTeacherAssignmentRepository;
use App\Repositories\LessonPlanRepository;
use App\Repositories\ParentLinkRepository;
use App\Services\ScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §6 (Phase 4 test gate,
 * extending Phase 1's per 14 §4)
 *
 * One test per row of 17 §6's table, in the same order, matching
 * Phase1TestGateTest.php's own "one test per gate row" shape. Every test
 * goes through the real repositories/models this task built rather than
 * asserting against a standalone boolean, same posture as
 * ScopeServiceTest's own doc comment.
 *
 * Row 5 (the automated route-table lint, F22 extended) is now fully
 * closed, not partially: an earlier pass left it owed for
 * AssignmentSubmission and ExamMark specifically (grading/marks-entry
 * had no controller or route at all), and a later pass closed that gap —
 * StudentPortal\AssignmentController::submit(),
 * TeacherPortal\AssignmentController::grade(), and
 * TeacherPortal\ExamController::recordMark() are wired into
 * Phase1TestGateTest::test_every_show_by_id_route_has_a_registered_scope_check()'s
 * existing route-table walk the same way the four generic resources
 * already were (see tests/Feature/StudentPortal/AssignmentControllerTest
 * and tests/Feature/TeacherPortal/{AssignmentControllerTest,
 * ExamControllerTest} for the per-controller HTTP tests exercising those
 * routes directly, and this file's own Row 2 for the ScopeService-level
 * coverage that predates them). Admin\LessonPlanController::approve()/
 * reject() close the remaining Row 4 gap the same way — see
 * tests/Feature/Admin/LessonPlanControllerTest.
 */
class Phase4TestGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * Row 1 — "A teacher with no class_teacher_assignments row for a
     * section cannot read/write that section's timetable, lesson plans,
     * assignments, or exams."
     *
     * Regresses: F18, generalized to all four class_section_id-carrying
     * Phase 4 resource types. Each carries class_section_id directly, so
     * this is confirming ScopeService's existing generic dispatch (no new
     * code, per 17 §3) actually covers all four model classes, not just
     * the ClassSection/StudentEnrollment slice ScopeServiceTest already
     * exercises.
     */
    public function test_teacher_without_class_teacher_assignment_cannot_read_any_of_the_four_generic_resources(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $homeroomTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $homeroomTeacher);

        $assignedTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        (new ClassTeacherAssignmentRepository())->assign(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $subject->id,
            $assignedTeacher->id,
            $admin
        );

        $unassignedTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $timetableSlot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $assignedTeacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $assignedTeacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $assignedTeacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);

        $scopeService = new ScopeService();

        foreach ([
            [TimetableSlot::class, $timetableSlot->id],
            [LessonPlan::class, $lessonPlan->id],
            [Assignment::class, $assignment->id],
            [Exam::class, $exam->id],
        ] as [$modelClass, $rowId]) {
            $this->assertSame(
                [$rowId],
                $scopeService->relationshipScope($modelClass::query(), $assignedTeacher, $modelClass)->pluck('id')->all(),
                "{$modelClass} must be visible to the teacher assigned to its class_section."
            );

            $this->assertSame(
                [],
                $scopeService->relationshipScope($modelClass::query(), $unassignedTeacher, $modelClass)->pluck('id')->all(),
                "{$modelClass} must not be visible to a teacher with no class_teacher_assignments row for its class_section."
            );
        }
    }

    /**
     * Row 2 — "A teacher cannot grade a submission (or enter a mark)
     * belonging to an assignment/exam on a section they aren't assigned
     * to, even though the submission/mark row itself has no
     * class_section_id."
     *
     * Regresses: F29, directly. Three teachers, not two: an assigned
     * teacher (admitted), a teacher with no assignment anywhere
     * (denied), and — the case a naive "does this teacher have *any*
     * class_teacher_assignments row" implementation would wrongly admit —
     * a teacher assigned to a *different* section entirely. All three
     * exercise the new assignment_id/exam_id join branch in
     * ScopeService::teacherRelationshipScope(), not the class_section_id
     * branch these two models don't carry.
     */
    public function test_teacher_cannot_grade_submission_or_mark_outside_their_assigned_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $sectionATeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $sectionA = $this->makeClassSection($school, $academicYear, $sectionATeacher);
        $sectionBTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $sectionB = $this->makeClassSection($school, $academicYear, $sectionBTeacher);

        $assignedTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        (new ClassTeacherAssignmentRepository())->assign(
            $school->id,
            $academicYear->id,
            $sectionA->id,
            $subject->id,
            $assignedTeacher->id,
            $admin
        );

        // Assigned to section B, not section A — the case a
        // "has any class_teacher_assignments row at all" bug would
        // wrongly let through.
        $differentSectionTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        (new ClassTeacherAssignmentRepository())->assign(
            $school->id,
            $academicYear->id,
            $sectionB->id,
            $subject->id,
            $differentSectionTeacher->id,
            $admin
        );

        // No class_teacher_assignments row anywhere.
        $unassignedTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $assignment = $this->makeAssignment($school, $academicYear, $sectionA, $subject, $assignedTeacher);
        $submission = $this->makeAssignmentSubmission($school, $assignment, $student);

        $exam = $this->makeExam($school, $academicYear, $sectionA, $subject);
        $mark = $this->makeExamMark($school, $exam, $student, $admin);

        $scopeService = new ScopeService();

        foreach ([
            [AssignmentSubmission::class, $submission->id],
            [ExamMark::class, $mark->id],
        ] as [$modelClass, $rowId]) {
            $this->assertSame(
                [$rowId],
                $scopeService->relationshipScope($modelClass::query(), $assignedTeacher, $modelClass)->pluck('id')->all(),
                "{$modelClass}: the teacher assigned to the parent's class_section must be able to grade it."
            );

            $this->assertSame(
                [],
                $scopeService->relationshipScope($modelClass::query(), $unassignedTeacher, $modelClass)->pluck('id')->all(),
                "{$modelClass}: a teacher with no class_teacher_assignments row anywhere must not be able to grade it."
            );

            $this->assertSame(
                [],
                $scopeService->relationshipScope($modelClass::query(), $differentSectionTeacher, $modelClass)->pluck('id')->all(),
                "{$modelClass}: a teacher assigned to a *different* section must not be able to grade it — "
                . 'F29\'s exact regression, not just the no-assignment-at-all case.'
            );
        }
    }

    /**
     * Row 3 — "A parent/student cannot fetch another student's
     * submission, mark, lesson plan, or timetable via any endpoint that
     * takes an ID."
     *
     * Regresses: F20/F26, generalized. Confirms — rather than exercises —
     * that the existing generic student_id dispatch
     * (parentRelationshipScope()/studentRelationshipScope()) covers
     * AssignmentSubmission/ExamMark with no new code, same as 17 §3
     * describes.
     *
     * LessonPlan/TimetableSlot now get the positive half of this row
     * asserted too, alongside the negative half (an unrelated
     * parent/student sees neither): parentRelationshipScope()/
     * studentRelationshipScope() gained a class_section_id branch
     * (resolved via the linked/own student's StudentEnrollment row(s),
     * same join style teacherRelationshipScope() already uses for its
     * assignment_id/exam_id branches) that closes the gap this doc
     * comment used to flag — see ScopeService::parentRelationshipScope()/
     * studentRelationshipScope()'s own doc comments for the fix. A
     * correctly linked parent / enrolled student now sees their own
     * child's/own class_section's LessonPlan and TimetableSlot rows, not
     * an empty result.
     */
    public function test_parent_and_student_cannot_fetch_another_students_submission_mark_lesson_plan_or_timetable(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);

        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $ownStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $otherStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        (new ParentLinkRepository())->link($school->id, $parent->id, $ownStudent->id, $admin);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $ownStudent);

        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);
        $ownSubmission = $this->makeAssignmentSubmission($school, $assignment, $ownStudent);
        $otherSubmission = $this->makeAssignmentSubmission($school, $assignment, $otherStudent);

        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);
        $ownMark = $this->makeExamMark($school, $exam, $ownStudent, $admin);
        $otherMark = $this->makeExamMark($school, $exam, $otherStudent, $admin);

        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher);
        $timetableSlot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);

        $scopeService = new ScopeService();

        // student_id-keyed resources: parent sees only their linked
        // child's row, never the other student's — the existing generic
        // dispatch, exercised against these two new model shapes.
        $this->assertSame(
            [$ownSubmission->id],
            $scopeService->relationshipScope(AssignmentSubmission::query(), $parent, AssignmentSubmission::class)->pluck('id')->all()
        );
        $this->assertSame(
            [$ownMark->id],
            $scopeService->relationshipScope(ExamMark::query(), $parent, ExamMark::class)->pluck('id')->all()
        );
        $this->assertSame(
            [$ownSubmission->id],
            $scopeService->relationshipScope(AssignmentSubmission::query(), $ownStudent, AssignmentSubmission::class)->pluck('id')->all()
        );
        $this->assertSame(
            [$ownMark->id],
            $scopeService->relationshipScope(ExamMark::query(), $ownStudent, ExamMark::class)->pluck('id')->all()
        );
        $this->assertNotContains($otherSubmission->id, $scopeService->relationshipScope(AssignmentSubmission::query(), $ownStudent, AssignmentSubmission::class)->pluck('id')->all());
        $this->assertNotContains($otherMark->id, $scopeService->relationshipScope(ExamMark::query(), $ownStudent, ExamMark::class)->pluck('id')->all());

        // class_section_id-keyed resources: the parent/student sees the
        // linked/own child's class_section content — including a
        // classmate's shared lesson plan/timetable slot, since visibility
        // here is at the section level, not the per-student level (a
        // lesson plan or timetable slot has no per-student identity to
        // narrow further — every student in the section legitimately
        // sees the same row). $ownStudent is enrolled into $classSection
        // above, so the linked parent and the student themself must see
        // both rows.
        $this->assertSame(
            [$lessonPlan->id],
            $scopeService->relationshipScope(LessonPlan::query(), $parent, LessonPlan::class)->pluck('id')->all(),
            "A parent linked to a student enrolled in the lesson plan's class_section must see it."
        );
        $this->assertSame(
            [$timetableSlot->id],
            $scopeService->relationshipScope(TimetableSlot::query(), $parent, TimetableSlot::class)->pluck('id')->all(),
            "A parent linked to a student enrolled in the timetable slot's class_section must see it."
        );
        $this->assertSame(
            [$lessonPlan->id],
            $scopeService->relationshipScope(LessonPlan::query(), $ownStudent, LessonPlan::class)->pluck('id')->all(),
            "A student enrolled in the lesson plan's class_section must see it."
        );
        $this->assertSame(
            [$timetableSlot->id],
            $scopeService->relationshipScope(TimetableSlot::query(), $ownStudent, TimetableSlot::class)->pluck('id')->all(),
            "A student enrolled in the timetable slot's class_section must see it."
        );

        // Negative half: a parent/student with NO link/enrollment into
        // this section sees neither.
        $unlinkedParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $unrelatedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $this->assertSame(
            [],
            $scopeService->relationshipScope(LessonPlan::query(), $unlinkedParent, LessonPlan::class)->pluck('id')->all()
        );
        $this->assertSame(
            [],
            $scopeService->relationshipScope(TimetableSlot::query(), $unrelatedStudent, TimetableSlot::class)->pluck('id')->all()
        );
    }

    /**
     * Not a numbered gate row — this is the Row 3 gap itself (see that
     * test's updated doc comment above), covering the two model classes
     * the Row 3 test doesn't touch (Assignment, Exam) and confirming
     * tenant isolation underneath the new class_section_id branch, same
     * shape as test_teacher_relationship_scope_branch_cannot_be_triggered_across_schools()
     * below does for the teacher side.
     *
     * Regresses: the gap flagged in 17-schoolos-academic-domain-map.md
     * §3 / this task's own Phase 4 report item 4 — parentRelationshipScope()/
     * studentRelationshipScope() previously dispatched only on student_id,
     * so a correctly linked parent or enrolled student got an empty
     * result for any class_section_id-only model instead of their own
     * child's/own section's rows.
     */
    public function test_parent_and_student_see_their_linked_childs_or_own_class_section_content_across_all_four_generic_resources(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);

        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        (new ParentLinkRepository())->link($school->id, $parent->id, $student->id, $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]));
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $timetableSlot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher);
        $assignment = $this->makeAssignment($school, $academicYear, $classSection, $subject, $teacher);
        $exam = $this->makeExam($school, $academicYear, $classSection, $subject);

        $scopeService = new ScopeService();

        foreach ([
            [TimetableSlot::class, $timetableSlot->id],
            [LessonPlan::class, $lessonPlan->id],
            [Assignment::class, $assignment->id],
            [Exam::class, $exam->id],
        ] as [$modelClass, $rowId]) {
            $this->assertSame(
                [$rowId],
                $scopeService->relationshipScope($modelClass::query(), $parent, $modelClass)->pluck('id')->all(),
                "{$modelClass} must be visible to a parent linked to a student enrolled in its class_section."
            );
            $this->assertSame(
                [$rowId],
                $scopeService->relationshipScope($modelClass::query(), $student, $modelClass)->pluck('id')->all(),
                "{$modelClass} must be visible to a student enrolled in its class_section."
            );
        }

        // Unlinked parent / unrelated student: denied, same as the Row 3
        // test's negative half.
        $unlinkedParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $unrelatedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        foreach ([TimetableSlot::class, LessonPlan::class, Assignment::class, Exam::class] as $modelClass) {
            $this->assertSame(
                [],
                $scopeService->relationshipScope($modelClass::query(), $unlinkedParent, $modelClass)->pluck('id')->all(),
                "{$modelClass} must not be visible to a parent with no active link to any enrolled student."
            );
            $this->assertSame(
                [],
                $scopeService->relationshipScope($modelClass::query(), $unrelatedStudent, $modelClass)->pluck('id')->all(),
                "{$modelClass} must not be visible to a student with no enrollment into its class_section."
            );
        }

        // Tenant isolation underneath the new branch: another school's
        // same-shaped rows, reached via a same-numbered class_section_id,
        // must stay invisible even though the branch never calls
        // tenantScope() itself — same posture as
        // test_teacher_relationship_scope_branch_cannot_be_triggered_across_schools().
        $otherSchool = $this->makeSchool();
        $otherAcademicYear = $this->makeAcademicYear($otherSchool);
        $otherSubject = $this->makeSubject($otherSchool);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $otherSchool->id]);
        $otherSection = $this->makeClassSection($otherSchool, $otherAcademicYear, $otherTeacher);
        $otherStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $otherSchool->id]);
        $this->makeStudentEnrollment($otherSchool, $otherAcademicYear, $otherSection, $otherStudent);

        $otherSchoolLessonPlan = $this->makeLessonPlan($otherSchool, $otherAcademicYear, $otherSection, $otherSubject, $otherTeacher);
        $otherSchoolTimetableSlot = $this->makeTimetableSlot($otherSchool, $otherAcademicYear, $otherSection, $otherSubject, $otherTeacher);

        $this->assertNotContains(
            $otherSchoolLessonPlan->id,
            $scopeService->relationshipScope(LessonPlan::query(), $parent, LessonPlan::class)->pluck('id')->all(),
            "A parent must not see another school's LessonPlan via the new class_section_id branch."
        );
        $this->assertNotContains(
            $otherSchoolTimetableSlot->id,
            $scopeService->relationshipScope(TimetableSlot::query(), $student, TimetableSlot::class)->pluck('id')->all(),
            "A student must not see another school's TimetableSlot via the new class_section_id branch."
        );
    }

    /**
     * Not a numbered gate row, but required by this task's own report
     * item 2: "whether the new teacherRelationshipScope() branch could be
     * triggered for a submission/mark whose parent Assignment/Exam
     * belongs to another school entirely."
     *
     * teacherRelationshipScope() resolves $assignedSectionIds from the
     * teacher's own class_teacher_assignments rows before ever touching
     * Assignment/Exam — and ClassTeacherAssignmentRepository::assign()
     * only ever writes a row whose class_section_id belongs to the
     * caller's own school (it rejects a cross-school class_section_id
     * outright — see that repository's own tests). So $assignedSectionIds
     * can never contain another school's section id in the first place;
     * the join through Assignment::whereIn('class_section_id', ...) is
     * tenant-safe by construction, not because an explicit school_id
     * check runs inside this branch. This test proves that directly: a
     * same-ID-space submission/mark belonging to a different school's
     * assignment/exam is invisible to a teacher who is validly assigned
     * to a same-numbered section in their own school, with no
     * tenantScope() call in this test at all — confirming the safety
     * doesn't depend on a caller remembering to intersect tenantScope()
     * first (though every real call site still does, per 12 §3a).
     */
    public function test_teacher_relationship_scope_branch_cannot_be_triggered_across_schools(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $otherAcademicYear = $this->makeAcademicYear($otherSchool);
        $subject = $this->makeSubject($school);
        $otherSubject = $this->makeSubject($otherSchool);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $otherAdmin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $otherSchool->id]);

        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);
        (new ClassTeacherAssignmentRepository())->assign(
            $school->id,
            $academicYear->id,
            $section->id,
            $subject->id,
            $teacher->id,
            $admin
        );

        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $otherSchool->id]);
        $otherSection = $this->makeClassSection($otherSchool, $otherAcademicYear, $otherTeacher);

        $otherSchoolStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $otherSchool->id]);
        $otherSchoolAssignment = $this->makeAssignment($otherSchool, $otherAcademicYear, $otherSection, $otherSubject, $otherTeacher);
        $otherSchoolSubmission = $this->makeAssignmentSubmission($otherSchool, $otherSchoolAssignment, $otherSchoolStudent);

        $otherSchoolExam = $this->makeExam($otherSchool, $otherAcademicYear, $otherSection, $otherSubject);
        $otherSchoolMark = $this->makeExamMark($otherSchool, $otherSchoolExam, $otherSchoolStudent, $otherAdmin);

        $scopeService = new ScopeService();

        // Deliberately calling relationshipScope() alone, with no
        // tenantScope() intersected first — the branch itself must not
        // leak another school's row, not merely "the combination with
        // tenantScope() happens to filter it out downstream."
        $this->assertSame(
            [],
            $scopeService->relationshipScope(AssignmentSubmission::query(), $teacher, AssignmentSubmission::class)->pluck('id')->all(),
            'A validly-assigned teacher must not see another school\'s AssignmentSubmission via the new branch.'
        );
        $this->assertSame(
            [],
            $scopeService->relationshipScope(ExamMark::query(), $teacher, ExamMark::class)->pluck('id')->all(),
            'A validly-assigned teacher must not see another school\'s ExamMark via the new branch.'
        );

        // Sanity: the rows exist and are visible to the *other* school's
        // own assigned teacher — confirms the emptiness above is tenant
        // isolation, not the fixtures failing to create anything.
        (new ClassTeacherAssignmentRepository())->assign(
            $otherSchool->id,
            $otherAcademicYear->id,
            $otherSection->id,
            $otherSubject->id,
            $otherTeacher->id,
            $otherAdmin
        );
        $this->assertSame(
            [$otherSchoolSubmission->id],
            $scopeService->relationshipScope(AssignmentSubmission::query(), $otherTeacher, AssignmentSubmission::class)->pluck('id')->all()
        );
        $this->assertSame(
            [$otherSchoolMark->id],
            $scopeService->relationshipScope(ExamMark::query(), $otherTeacher, ExamMark::class)->pluck('id')->all()
        );
    }

    /**
     * Row 4 — "Approving or rejecting a LessonPlan requires both the
     * reviewer's role (school_admin) and matching tenant_id — a
     * school_admin from another school cannot approve it, and a teacher
     * cannot approve it regardless of tenant."
     *
     * Regresses: F30, F31. Exercises LessonPlanRepository::approve()/
     * reject() directly — the one write path for these columns, per that
     * repository's own doc comment.
     */
    public function test_lesson_plan_approval_requires_matching_tenant_and_school_admin_role(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);

        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $lessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher, ['status' => 'submitted']);

        $ownAdmin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $otherSchoolAdmin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $otherSchool->id]);

        $repository = new LessonPlanRepository();

        // A school_admin from another school cannot approve it — the
        // tenant half of F30/F31's fix. tenantScope() makes "wrong
        // school" and "doesn't exist" indistinguishable, so this is the
        // same exception type as the role failure below.
        try {
            $repository->approve($lessonPlan->id, $otherSchoolAdmin);
            $this->fail('Expected UnauthorizedLessonPlanReviewFailure for a cross-tenant school_admin.');
        } catch (UnauthorizedLessonPlanReviewFailure $e) {
            $this->assertSame($otherSchoolAdmin->id, $e->actor->id);
        }

        $lessonPlan->refresh();
        $this->assertSame('submitted', $lessonPlan->status, 'A cross-tenant approval attempt must not have written anything.');

        // A teacher cannot approve it regardless of tenant — the role
        // half of F30/F31's fix, checked even for a teacher in the
        // correct school.
        try {
            $repository->approve($lessonPlan->id, $teacher);
            $this->fail('Expected UnauthorizedLessonPlanReviewFailure for a teacher actor.');
        } catch (UnauthorizedLessonPlanReviewFailure $e) {
            $this->assertSame($teacher->id, $e->actor->id);
        }

        $lessonPlan->refresh();
        $this->assertSame('submitted', $lessonPlan->status, 'A wrong-role approval attempt must not have written anything.');

        // The correct actor — school_admin, own tenant — succeeds.
        $approved = $repository->approve($lessonPlan->id, $ownAdmin, 'Looks good.');
        $this->assertSame('approved', $approved->status);
        $this->assertSame($ownAdmin->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);

        // reject() carries the same two checks — confirmed against a
        // second lesson plan rather than re-reviewing the now-approved
        // one above.
        $secondLessonPlan = $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher, ['status' => 'submitted']);

        try {
            $repository->reject($secondLessonPlan->id, $otherSchoolAdmin, 'Not acceptable.');
            $this->fail('Expected UnauthorizedLessonPlanReviewFailure for a cross-tenant reject().');
        } catch (UnauthorizedLessonPlanReviewFailure) {
            // Expected.
        }

        $rejected = $repository->reject($secondLessonPlan->id, $ownAdmin, 'Needs more detail.');
        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('Needs more detail.', $rejected->review_note);
    }

    /**
     * Row 5 — "A school_admin sees every lesson plan/assignment/exam/
     * timetable entry in their own school without needing
     * $wideVisibilityRoles."
     *
     * Confirms 17 §4's finding is actually true in the built system: the
     * default branch of relationshipScope() (no role match → query
     * unchanged) is what gives school_admin full within-school
     * visibility, not a $wideVisibilityRoles entry — none of these four
     * calls pass one.
     */
    public function test_school_admin_sees_every_lesson_plan_assignment_exam_and_timetable_entry_without_wide_visibility_roles(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $teacherA = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $sectionA = $this->makeClassSection($school, $academicYear, $teacherA);
        $teacherB = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $sectionB = $this->makeClassSection($school, $academicYear, $teacherB);

        // Deliberately spread across two sections neither teacher has any
        // relationship to the other's — school_admin must still see both,
        // with no per-teacher assignment involved at all.
        $lessonPlanA = $this->makeLessonPlan($school, $academicYear, $sectionA, $subject, $teacherA);
        $lessonPlanB = $this->makeLessonPlan($school, $academicYear, $sectionB, $subject, $teacherB);
        $assignmentA = $this->makeAssignment($school, $academicYear, $sectionA, $subject, $teacherA);
        $assignmentB = $this->makeAssignment($school, $academicYear, $sectionB, $subject, $teacherB);
        $examA = $this->makeExam($school, $academicYear, $sectionA, $subject);
        $examB = $this->makeExam($school, $academicYear, $sectionB, $subject);
        $slotA = $this->makeTimetableSlot($school, $academicYear, $sectionA, $subject, $teacherA);
        $slotB = $this->makeTimetableSlot($school, $academicYear, $sectionB, $subject, $teacherB);

        $scopeService = new ScopeService();

        $this->assertEqualsCanonicalizing(
            [$lessonPlanA->id, $lessonPlanB->id],
            $scopeService->relationshipScope(LessonPlan::query(), $admin, LessonPlan::class)->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$assignmentA->id, $assignmentB->id],
            $scopeService->relationshipScope(Assignment::query(), $admin, Assignment::class)->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$examA->id, $examB->id],
            $scopeService->relationshipScope(Exam::query(), $admin, Exam::class)->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$slotA->id, $slotB->id],
            $scopeService->relationshipScope(TimetableSlot::query(), $admin, TimetableSlot::class)->pluck('id')->all()
        );
    }
}
