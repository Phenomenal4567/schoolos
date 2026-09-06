<?php

namespace Tests\Feature;

use App\Exceptions\Auth\UserNotFoundFailure;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\ScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use ReflectionMethod;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 14-schoolos-implementation-plan.md §1 (Phase 1 test gate table)
 *
 * One test per row of the table, in the same order the table lists them.
 * Each test's docblock names the finding(s) it regresses against, per the
 * task instruction that this gate be an actual, runnable suite rather than
 * a checklist.
 *
 * Row 6 (teacher relationshipScope) is now a real, passing test —
 * ScopeService::teacherRelationshipScope() is implemented and exercised
 * against student_enrollments, the one resource that fits its join
 * shape today; the homework/assignment half of the row stays deferred in
 * a comment, since those tables don't exist until Phase 4. Row 7 (parent/
 * student relationshipScope) is now real too, for the same buildable
 * slice — student_enrollments via the student_id-column path — with
 * attendance/leave applications noted as still deferred (Phase 3/4, no
 * table yet). Row 8 (the automated route-table lint) still depends on
 * functionality this pass explicitly defers to Phase 2+ — no resource
 * controllers/routes exist yet for the lint to run against. It stays
 * skipped with an explicit reason rather than either faking a pass or
 * being silently omitted from the suite — the row stays present and
 * named so it's visibly still owed, not forgotten.
 */
class Phase1TestGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * Row 1 — "Login by email, mobile, and registration number all
     * succeed via the same authenticate() call."
     *
     * Regresses: F5 (registration-number login crash), and generally the
     * "four validators that disagreed on which field to check" defect
     * this whole service exists to close.
     */
    public function test_login_succeeds_via_email_mobile_and_registration_number(): void
    {
        $service = new AuthenticationService();

        $byEmail = $this->makeUser(['email' => 'byemail@example.test']);
        $byMobile = $this->makeUser(['email' => null, 'mobile_no' => '+15551234567']);
        $byRegNo = $this->makeUser(['email' => null, 'registration_number' => 'REG-2026-001']);

        $resolvedEmail = $service->resolveIdentifier('byemail@example.test');
        $this->assertSame('email', $resolvedEmail['type']);
        $this->assertTrue(
            $byEmail->is($service->authenticate($resolvedEmail, 'correct-password'))
        );

        $resolvedMobile = $service->resolveIdentifier('+15551234567');
        $this->assertSame('mobile_no', $resolvedMobile['type']);
        $this->assertTrue(
            $byMobile->is($service->authenticate($resolvedMobile, 'correct-password'))
        );

        $resolvedRegNo = $service->resolveIdentifier('REG-2026-001');
        $this->assertSame('registration_number', $resolvedRegNo['type']);
        $this->assertTrue(
            $byRegNo->is($service->authenticate($resolvedRegNo, 'correct-password'))
        );
    }

    /**
     * Row 2 — "A (mobile_no, usergroup)-shaped pair matching no user
     * returns a typed failure, not a 500."
     *
     * Regresses: F16 (uncaught \Error from a null-property dereference in
     * the reset-flow's failure path, which skipped every catch(Exception)
     * block written to handle it). authenticate() has no `usergroup`
     * parameter at all (see the F4 test below), so this test's job is
     * narrower and more literal than the original bug: confirm a
     * mobile_no that matches no user produces a catchable UserNotFoundFailure,
     * never an uncaught \Error or any other uncatchable \Throwable.
     */
    public function test_unmatched_mobile_no_returns_typed_failure_not_uncaught_error(): void
    {
        $service = new AuthenticationService();

        $resolved = $service->resolveIdentifier('+19995550000');
        $this->assertSame('mobile_no', $resolved['type']);

        try {
            $service->authenticate($resolved, 'irrelevant-password');
            $this->fail('Expected UserNotFoundFailure to be thrown.');
        } catch (UserNotFoundFailure $e) {
            // Caught as a plain \Exception subclass — this is the
            // assertion that matters for F16: it's reachable by a normal
            // catch(\Exception) or catch(UserNotFoundFailure) block, not
            // only by catch(\Throwable).
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Row 3 — "Login cannot be influenced by a value matching another
     * user's display name."
     *
     * Regresses: F6 (`checkschool`'s `orWhere('name', ...)` clause could
     * resolve to the wrong user).
     */
    public function test_login_ignores_display_name_matches(): void
    {
        $service = new AuthenticationService();

        // decoyOwner's *name* happens to look like the string someone
        // else will try to log in with. If `name` were ever consulted,
        // this identifier would incorrectly resolve to decoyOwner.
        $this->makeUser([
            'name' => 'realaccount@example.test',
            'email' => 'decoy-owner@example.test',
        ]);

        $realAccount = $this->makeUser(['email' => 'realaccount@example.test']);

        $resolved = $service->resolveIdentifier('realaccount@example.test');
        $this->assertSame('email', $resolved['type']);

        $authenticated = $service->authenticate($resolved, 'correct-password');

        $this->assertTrue($realAccount->is($authenticated));
    }

    /**
     * Row 4 — "usergroup/role is never accepted as request input during
     * authentication or password reset."
     *
     * Regresses: F4 (client-supplied `usergroup` used as a raw
     * disambiguator in the OTP/reset flow).
     *
     * This is checked structurally via reflection rather than by trying
     * to sneak a `usergroup` key past authenticate() at runtime: the
     * method's signature is `authenticate(array $identifier, string
     * $credential)` — there is no third parameter for role/usergroup to
     * occupy, so there is no call shape that could pass one in. Asserting
     * the signature directly is a stronger guarantee than a behavioral
     * test that could pass by accident (e.g. if an extra array key were
     * silently ignored today but read by a future edit).
     */
    public function test_authenticate_signature_has_no_usergroup_or_role_parameter(): void
    {
        $method = new ReflectionMethod(AuthenticationService::class, 'authenticate');
        $paramNames = array_map(
            static fn ($p) => strtolower($p->getName()),
            $method->getParameters()
        );

        $this->assertCount(2, $paramNames, 'authenticate() must take exactly identifier + credential.');
        $this->assertNotContains('usergroup', $paramNames);
        $this->assertNotContains('role', $paramNames);
        $this->assertNotContains('role_id', $paramNames);

        // Belt-and-braces: role is resolved from the looked-up user, never
        // taken from the caller. Confirm authenticate() returns a user
        // whose role was already fixed at creation time, regardless of
        // what (if anything) a caller might have supplied elsewhere in
        // the same request — there is simply nowhere for that value to
        // enter this method.
        $service = new AuthenticationService();
        $teacherRole = $this->makeRole('teacher');
        $user = $this->makeUser(['role_id' => $teacherRole->id, 'email' => 'fixed-role@example.test']);

        $resolved = $service->resolveIdentifier('fixed-role@example.test');
        $authenticated = $service->authenticate($resolved, 'correct-password');

        $this->assertSame($teacherRole->id, $authenticated->role_id);
    }

    /**
     * Row 5 — "Superadmin cross-school access goes through the one
     * ScopeService exemption path, not a per-query conditional."
     *
     * Regresses: F3 (scattered `usergroup_id == 1` bypass checks).
     */
    public function test_superadmin_tenant_scope_exemption_is_the_single_bypass_path(): void
    {
        $scopeService = new ScopeService();

        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();

        $superAdminRole = $this->makeRole('super_admin');
        $superAdmin = $this->makeUser([
            'school_id' => null,
            'role_id' => $superAdminRole->id,
            'email' => 'super@example.test',
        ]);

        $schoolAdminRole = $this->makeRole('school_admin');
        $userInSchoolA = $this->makeUser([
            'school_id' => $schoolA->id,
            'role_id' => $schoolAdminRole->id,
            'email' => 'admin-a@example.test',
        ]);
        $this->makeUser([
            'school_id' => $schoolB->id,
            'role_id' => $schoolAdminRole->id,
            'email' => 'admin-b@example.test',
        ]);

        // Superadmin (school_id === null): tenantScope() must be a no-op,
        // returning users from every school.
        $superAdminResults = $scopeService
            ->tenantScope(User::query(), $superAdmin)
            ->pluck('id')
            ->all();
        $this->assertCount(3, $superAdminResults);

        // A regular, school-bound user: tenantScope() must constrain to
        // exactly their own school — the exemption above must not leak
        // into the non-superadmin path.
        $scopedResults = $scopeService
            ->tenantScope(User::query(), $userInSchoolA)
            ->pluck('id')
            ->all();
        $this->assertEqualsCanonicalizing([$userInSchoolA->id], $scopedResults);
    }

    /**
     * Row 6 — "A teacher with no class_teacher_assignments row for a
     * class cannot read/write that class's students, homework, or
     * assignments."
     *
     * Regresses: F18 (teacher Gates checked tenant only, never consulted
     * class_teacher_assignments).
     *
     * The "students" part is now real: ScopeService::teacherRelationshipScope()
     * is implemented (generic join on class_section_id, intersected with
     * academic_year_id/subject_id when present) and student_enrollments
     * exercises it directly — see
     * ScopeServiceTest::test_teacher_relationship_scope_admits_assigned_teacher_and_denies_unassigned()
     * for the full assertion. The "homework, or assignments" part is
     * still not runnable: those tables don't exist until Phase 4, so
     * there's nothing yet to query. Once they're added (with a
     * class_section_id column, per 12 §3a's design), the same join
     * applies to them without changes to ScopeService itself.
     */
    public function test_teacher_without_class_teacher_assignment_is_denied(): void
    {
        $scopeService = new ScopeService();
        $teacherRole = $this->makeRole('teacher');

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);

        $classTeacher = $this->makeUser(['role_id' => $teacherRole->id, 'school_id' => $school->id, 'email' => 'class-teacher@example.test']);
        $unassignedTeacher = $this->makeUser(['role_id' => $teacherRole->id, 'school_id' => $school->id, 'email' => 'no-assignment@example.test']);

        $classSection = $this->makeClassSection($school, $academicYear, $classTeacher);
        $this->makeClassTeacherAssignment($school, $academicYear, $classSection, $subject, $classTeacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $visible = $scopeService
            ->relationshipScope(StudentEnrollment::query(), $unassignedTeacher, StudentEnrollment::class)
            ->pluck('id')
            ->all();

        $this->assertSame(
            [],
            $visible,
            'A teacher with no class_teacher_assignments row for this class must not see its roster.'
        );

        // homework/assignments: still deferred to Phase 4, no table
        // exists yet — see ScopeService::teacherRelationshipScope()'s
        // doc comment on why the same join is expected to cover them.
    }

    /**
     * Row 7 — "A parent/student cannot fetch another student's record via
     * relationshipScope, for every resource type scoped this way."
     *
     * Regresses: F20 (attendance IDOR via caller-supplied student_id with
     * no student_parent_links check), F21 (leave-application surface with
     * no scoping at all).
     *
     * The student_enrollments slice is now real:
     * ScopeService::parentRelationshipScope() and studentRelationshipScope()
     * are implemented (student_id-column join for parent, direct
     * student_id/own-id constraint for student) and student_enrollments
     * exercises both directly — see ScopeServiceTest's
     * test_parent_relationship_scope_admits_linked_child_and_denies_unlinked_against_student_id_column()
     * and test_student_relationship_scope_admits_own_enrollment_and_denies_others()
     * for the full assertions. Attendance and leave applications — the
     * actual resources F20/F21 concern — stay deferred: those tables
     * don't exist until Phase 3/4, so there's nothing yet to query.
     */
    public function test_parent_and_student_cannot_fetch_another_students_record(): void
    {
        $scopeService = new ScopeService();

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parentRole = $this->makeRole('parent');
        $studentRole = $this->makeRole('student');
        $teacherRole = $this->makeRole('teacher');

        $teacher = $this->makeUser(['role_id' => $teacherRole->id, 'school_id' => $school->id, 'email' => 'gate-teacher@example.test']);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);

        $parent = $this->makeUser(['role_id' => $parentRole->id, 'school_id' => $school->id, 'email' => 'gate-parent@example.test']);
        $studentA = $this->makeUser(['role_id' => $studentRole->id, 'school_id' => $school->id, 'email' => 'gate-student-a@example.test']);
        $studentB = $this->makeUser(['role_id' => $studentRole->id, 'school_id' => $school->id, 'email' => 'gate-student-b@example.test']);

        $enrollmentA = $this->makeStudentEnrollment($school, $academicYear, $classSection, $studentA);
        $enrollmentB = $this->makeStudentEnrollment($school, $academicYear, $classSection, $studentB);

        $this->makeStudentParentLink($school, $parent, $studentA);

        $visibleToParent = $scopeService
            ->relationshipScope(StudentEnrollment::query(), $parent, StudentEnrollment::class)
            ->pluck('id')
            ->all();
        $this->assertSame(
            [$enrollmentA->id],
            $visibleToParent,
            'A parent must see only their own linked child\'s enrollment, never another student\'s.'
        );

        $visibleToStudentB = $scopeService
            ->relationshipScope(StudentEnrollment::query(), $studentB, StudentEnrollment::class)
            ->pluck('id')
            ->all();
        $this->assertSame(
            [$enrollmentB->id],
            $visibleToStudentB,
            'A student must see only their own enrollment, never another student\'s.'
        );

        // attendance, leave applications: still deferred to Phase 3/4, no
        // table exists yet — see ScopeService::parentRelationshipScope()'s
        // and studentRelationshipScope()'s doc comments on why the same
        // column-presence join is expected to cover them once built.
    }

    /**
     * Row 8 — "Every controller action that resolves a single record by
     * ID has a registered scope check, enforced by an automated test/lint
     * over the route table — not manual review."
     *
     * Regresses: F22 (destroy checked Gate::allows(), the sibling show/
     * update methods on the same controller didn't — proving per-method
     * manual diligence isn't reliable even for the developers' own
     * known-correct pattern).
     *
     * No longer skipped: Phase 2's parent web dashboard added the first
     * App\Http\Controllers routes with a real route-parameter shape
     * (parent.children.show, GET /parent/children/{student}), so there is
     * now a route table to walk. A route is treated as "resolves a single
     * record by ID" when its URI contains a route parameter (e.g.
     * {student}) — index-style routes with no parameter, and framework
     * routes outside App\Http\Controllers (health checks, the welcome
     * route, login/logout — none of which resolve an app record by ID),
     * are excluded. Every matching route must carry the 'scope.checked'
     * middleware (MarkScopeChecked) — see that class's doc comment for
     * exactly what this lint can and can't verify: it confirms the route
     * wasn't silently left unmarked (F22's actual failure mode), not that
     * the controller body calls ScopeService correctly, which stays a
     * matter for the controller's own tests
     * (see ParentDashboardTest::test_parent_cannot_view_another_parents_child).
     */
    public function test_every_show_by_id_route_has_a_registered_scope_check(): void
    {
        /** @var Router $router */
        $router = app(Router::class);

        $checked = 0;

        foreach ($router->getRoutes() as $route) {
            $action = $route->getAction();
            $controller = is_string($action['controller'] ?? null) ? $action['controller'] : null;

            if ($controller === null || ! str_starts_with($controller, 'App\\Http\\Controllers\\')) {
                // Not one of ours — skips the closure-based '/' route and
                // any framework-registered route (e.g. '/up').
                continue;
            }

            $hasIdParameter = count($route->parameterNames()) > 0;

            if (! $hasIdParameter) {
                continue;
            }

            $checked++;

            $this->assertContains(
                'scope.checked',
                $route->gatherMiddleware(),
                "{$route->uri()} ({$controller}) resolves a record by route parameter but is "
                . "missing the 'scope.checked' middleware — every single-record-by-ID route must "
                . 'carry it, per 12 §3a and this row\'s F22 regression.'
            );
        }

        $this->assertGreaterThan(
            0,
            $checked,
            'No single-record-by-ID App\\Http\\Controllers routes were found to lint — if this '
            . 'fires, either the route table changed shape or this test\'s detection logic needs '
            . 'updating, not that the check should go back to being skipped.'
        );
    }
}
