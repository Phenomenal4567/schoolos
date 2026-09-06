<?php

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\AdmissionApplicationController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\CalendarEventController as AdminCalendarEventController;
use App\Http\Controllers\Admin\ClassSectionController;
use App\Http\Controllers\Admin\ClassTeacherAssignmentController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\ExamComponentController as AdminExamComponentController;
use App\Http\Controllers\Admin\ExamController as AdminExamController;
use App\Http\Controllers\Admin\ExpenseController as AdminExpenseController;
use App\Http\Controllers\Admin\ExportJobController;
use App\Http\Controllers\Admin\FeeAssessmentController as AdminFeeAssessmentController;
use App\Http\Controllers\Admin\FeeCategoryController as AdminFeeCategoryController;
use App\Http\Controllers\Admin\FinanceDashboardController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admin\PromotionRuleController;
use App\Http\Controllers\Admin\ScholarshipController as AdminScholarshipController;
use App\Http\Controllers\Admin\IdCardController;
use App\Http\Controllers\Admin\LearningMaterialController as AdminLearningMaterialController;
use App\Http\Controllers\Admin\LessonPlanController as AdminLessonPlanController;
use App\Http\Controllers\Admin\ParentLinkController;
use App\Http\Controllers\Admin\ParentEnrollmentController;
use App\Http\Controllers\Admin\SchemeOfWorkController as AdminSchemeOfWorkController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SchoolProfileController;
use App\Http\Controllers\Admin\SetupWizardController;
use App\Http\Controllers\Admin\StandardController;
use App\Http\Controllers\Admin\StaffAttendanceController as AdminStaffAttendanceController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\StaffProfileController as AdminStaffProfileController;
use App\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TimetableController as AdminTimetableController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\StaffAttendance\CheckInController as StaffCheckInController;
use App\Http\Controllers\StaffPortal\DashboardController as StaffPortalDashboardController;
use App\Http\Controllers\ParentPortal\AnnouncementController as ParentAnnouncementController;
use App\Http\Controllers\ParentPortal\AssignmentController as ParentAssignmentController;
use App\Http\Controllers\ParentPortal\CalendarEventController as ParentCalendarEventController;
use App\Http\Controllers\ParentPortal\ChildLinkRequestController;
use App\Http\Controllers\ParentPortal\DashboardController;
use App\Http\Controllers\ParentPortal\ExamController as ParentExamController;
use App\Http\Controllers\ParentPortal\FeeController as ParentFeeController;
use App\Http\Controllers\ParentPortal\FeedbackController as ParentFeedbackController;
use App\Http\Controllers\ParentPortal\LearningMaterialController as ParentLearningMaterialController;
use App\Http\Controllers\ParentPortal\LessonPlanController as ParentLessonPlanController;
use App\Http\Controllers\ParentPortal\NotificationController as ParentNotificationController;
use App\Http\Controllers\ParentPortal\PaymentController as ParentPaymentController;
use App\Http\Controllers\ParentPortal\PromotionController as ParentPromotionController;
use App\Http\Controllers\ParentPortal\SchemeOfWorkController as ParentSchemeOfWorkController;
use App\Http\Controllers\ParentPortal\TimetableController as ParentTimetableController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\Public\AdmissionApplicationController as PublicAdmissionApplicationController;
use App\Http\Controllers\Public\InvitationController as PublicInvitationController;
use App\Http\Controllers\Public\MarketingPageController;
use App\Http\Controllers\Public\ParentRegistrationController as PublicParentRegistrationController;
use App\Http\Controllers\Public\SchoolOnboardingController as PublicSchoolOnboardingController;
use App\Http\Controllers\StudentPortal\AnnouncementController as StudentAnnouncementController;
use App\Http\Controllers\StudentPortal\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\StudentPortal\CalendarEventController as StudentCalendarEventController;
use App\Http\Controllers\StudentPortal\DashboardController as StudentDashboardController;
use App\Http\Controllers\StudentPortal\ExamController as StudentExamController;
use App\Http\Controllers\StudentPortal\FeeController as StudentFeeController;
use App\Http\Controllers\StudentPortal\FeedbackController as StudentFeedbackController;
use App\Http\Controllers\StudentPortal\LearningMaterialController as StudentLearningMaterialController;
use App\Http\Controllers\StudentPortal\LessonPlanController as StudentLessonPlanController;
use App\Http\Controllers\StudentPortal\NotificationController as StudentNotificationController;
use App\Http\Controllers\StudentPortal\PromotionController as StudentPromotionController;
use App\Http\Controllers\StudentPortal\SchemeOfWorkController as StudentSchemeOfWorkController;
use App\Http\Controllers\StudentPortal\TimetableController as StudentTimetableController;
use App\Http\Controllers\SuperAdmin\AuditLogController as SuperAdminAuditLogController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\PlatformSettingController;
use App\Http\Controllers\SuperAdmin\SchoolAdminController as SuperAdminSchoolAdminController;
use App\Http\Controllers\SuperAdmin\SchoolController as SuperAdminSchoolController;
use App\Http\Controllers\TeacherPortal\AnnouncementController as TeacherAnnouncementController;
use App\Http\Controllers\TeacherPortal\AssignmentController as TeacherAssignmentController;
use App\Http\Controllers\TeacherPortal\AttendanceController;
use App\Http\Controllers\TeacherPortal\CalendarEventController as TeacherCalendarEventController;
use App\Http\Controllers\TeacherPortal\DashboardController as TeacherDashboardController;
use App\Http\Controllers\TeacherPortal\ExamController as TeacherExamController;
use App\Http\Controllers\TeacherPortal\LearningMaterialController as TeacherLearningMaterialController;
use App\Http\Controllers\TeacherPortal\LessonPlanController as TeacherLessonPlanController;
use App\Http\Controllers\TeacherPortal\NotificationController as TeacherNotificationController;
use App\Http\Controllers\TeacherPortal\ProfileController as TeacherProfileController;
use App\Http\Controllers\TeacherPortal\SchemeOfWorkController as TeacherSchemeOfWorkController;
use App\Http\Controllers\TeacherPortal\SubjectAttendanceController;
use App\Http\Controllers\TeacherPortal\TimetableController as TeacherTimetableController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::controller(MarketingPageController::class)
    ->name('marketing.')
    ->group(function () {
        Route::get('/features', 'show')->defaults('page', 'features')->name('features.index');
        Route::get('/features/student-management', 'show')->defaults('page', 'student-management')->name('features.student-management');
        Route::get('/features/attendance', 'show')->defaults('page', 'attendance')->name('features.attendance');
        Route::get('/features/academics', 'show')->defaults('page', 'academics')->name('features.academics');
        Route::get('/features/fees-payments', 'show')->defaults('page', 'fees-payments')->name('features.fees-payments');
        Route::get('/features/communication', 'show')->defaults('page', 'communication')->name('features.communication');
        Route::get('/features/reports', 'show')->defaults('page', 'reports')->name('features.reports');
        Route::get('/pricing', 'show')->defaults('page', 'pricing')->name('pricing');
        Route::get('/demo', 'show')->defaults('page', 'demo')->name('demo');
        Route::get('/solutions/school-admins', 'show')->defaults('page', 'school-admins')->name('solutions.school-admins');
        Route::get('/solutions/teachers', 'show')->defaults('page', 'teachers')->name('solutions.teachers');
        Route::get('/solutions/parents', 'show')->defaults('page', 'parents')->name('solutions.parents');
        Route::get('/solutions/students', 'show')->defaults('page', 'students')->name('solutions.students');
        Route::get('/solutions/staff', 'show')->defaults('page', 'staff')->name('solutions.staff');
        Route::get('/solutions/private-schools', 'show')->defaults('page', 'private-schools')->name('solutions.private-schools');
        Route::get('/solutions/multi-campus', 'show')->defaults('page', 'multi-campus')->name('solutions.multi-campus');
        Route::get('/help', 'show')->defaults('page', 'help')->name('help');
        Route::get('/docs', 'show')->defaults('page', 'docs')->name('docs');
        Route::get('/getting-started', 'show')->defaults('page', 'getting-started')->name('getting-started');
        Route::get('/faqs', 'show')->defaults('page', 'faqs')->name('faqs');
        Route::get('/support', 'show')->defaults('page', 'support')->name('support');
        Route::get('/about', 'show')->defaults('page', 'about')->name('about');
        Route::get('/contact', 'show')->defaults('page', 'contact')->name('contact');
        Route::get('/careers', 'show')->defaults('page', 'careers')->name('careers');
        Route::get('/partners', 'show')->defaults('page', 'partners')->name('partners');
        Route::get('/privacy', 'show')->defaults('page', 'privacy')->name('privacy');
        Route::get('/terms', 'show')->defaults('page', 'terms')->name('terms');
        Route::get('/cookies', 'show')->defaults('page', 'cookies')->name('cookies');
    });

/**
 * Design ref: 12-schoolos-architecture.md §2, 14-schoolos-implementation-plan.md §0
 *
 * One login/logout pair for every role — see SessionController's own doc
 * comment for why there's no per-role login route.
 */
Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('guest')
    ->prefix('get-started')
    ->name('public.onboarding.')
    ->group(function () {
        Route::get('/', [PublicSchoolOnboardingController::class, 'create'])->name('create');
        Route::post('/', [PublicSchoolOnboardingController::class, 'store'])->name('store');
    });

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §10, discovery
 * doc §12, track 7c. Decision ref:
 * 16-schoolos-decisions-register.md D16.
 *
 * Public, unauthenticated admission-application form — a prospective
 * applicant has no SchoolOS session, so this sits in the same
 * 'guest'-middleware shape already used for /login rather than any
 * role-gated group. Scoped to a school via a `?school=` query parameter,
 * not a URI route parameter — deliberately, not just stylistically:
 * Phase1TestGateTest's row 8 route-table lint (F22's regression) treats
 * *any* App\Http\Controllers route whose URI contains a route parameter
 * as "resolves a single record by ID" and requires 'scope.checked' on
 * it, with no built-in exemption for guest/unauthenticated routes (see
 * that test's own doc comment — it's a mechanical URI-shape check, not
 * a semantic one). Adding 'scope.checked' here would be false
 * advertising: MarkScopeChecked's own doc comment defines the marker as
 * asserting the controller calls ScopeService::tenantScope()/
 * relationshipScope(), which this controller has no actor to do. A
 * `?school=` query parameter isn't part of the route URI Laravel's
 * router reports via parameterNames(), so this genuinely isn't a
 * single-record-by-ID route in the shape row 8 is checking for — not a
 * workaround, the honest classification. School lookup by short_code
 * still happens server-side in the controller either way (Ground Rule
 * 0 — never trust a client-supplied numeric school_id), same as the
 * path-parameter version this replaces.
 *
 * store has no route-parameter-resolved record at all (it creates a
 * row, from a short_code-resolved school plus body data), matching
 * every other id-less store()-shaped route's exemption from row 8's
 * lint for the ordinary reason (no route parameter, not this
 * controller's query-string reasoning).
 */
Route::middleware('guest')
    ->prefix('apply')
    ->name('public.admissions.')
    ->group(function () {
        Route::get('/', [PublicAdmissionApplicationController::class, 'create'])->name('create');
        Route::post('/', [PublicAdmissionApplicationController::class, 'store'])->name('store');
    });

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
 * Invitation mechanism"
 *
 * Public invitation-acceptance page — same shape and same reasoning as
 * the /apply group directly above: 'guest' middleware, the token carried
 * as a `?token=` query string (not a route parameter) so this route is
 * honestly outside Phase1TestGateTest's row 8 scope-checked lint rather
 * than falsely marked — see Public\InvitationController's own doc
 * comment.
 */
Route::middleware('guest')
    ->prefix('invitations')
    ->name('public.invitations.')
    ->group(function () {
        Route::get('/accept', [PublicInvitationController::class, 'create'])->name('create');
        Route::post('/accept', [PublicInvitationController::class, 'store'])->name('store');
    });

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §4 (Parent
 * onboarding)
 *
 * Public parent self-registration — same shape and reasoning as /apply
 * and /invitations/accept above. Creates the parent account only; see
 * Public\ParentRegistrationController's own doc comment for why linking
 * to a student is a deliberately separate, authenticated, admin-approved
 * step.
 */
Route::middleware('guest')
    ->prefix('register/parent')
    ->name('public.parents.')
    ->group(function () {
        Route::get('/', [PublicParentRegistrationController::class, 'create'])->name('create');
        Route::post('/', [PublicParentRegistrationController::class, 'store'])->name('store');
    });

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/**
 * Design ref: 21-schoolos-finance-architecture.md §3, D13.
 *
 * Paystack's server-to-server webhook — see PaystackWebhookController's
 * own doc comment for why it's deliberately outside every role-gated
 * group above (no 'auth' middleware: Paystack has no SchoolOS session)
 * and why it's the one route in this app that needs the CSRF exemption
 * registered in bootstrap/app.php. No route parameter (identity comes
 * from the posted transaction reference, verified against Paystack's
 * own API inside the controller — never a route segment), so this is
 * exempt from row 8's 'scope.checked' lint the same way every other
 * id-less store()-shaped route in this file is.
 */
Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle'])
    ->name('webhooks.paystack');

Route::middleware(['auth', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

        /*
         * Design ref: SchoolOS Onboarding & Authentication UI — demo/
         * trial billing addition. No route parameter (mutates the
         * single PlatformSetting row, not one resolved by id) — exempt
         * from row 8's lint the same way every other id-less write
         * action in this bundle is.
         */
        Route::put('/platform-settings', [PlatformSettingController::class, 'update'])
            ->name('platform-settings.update');

        Route::get('/schools', [SuperAdminSchoolController::class, 'index'])->name('schools.index');

        Route::post('/schools', [SuperAdminSchoolController::class, 'store'])->name('schools.store');

        Route::get('/schools/{school}', [SuperAdminSchoolController::class, 'show'])
            ->middleware('scope.checked')
            ->name('schools.show');

        Route::put('/schools/{school}', [SuperAdminSchoolController::class, 'update'])
            ->middleware('scope.checked')
            ->name('schools.update');

        Route::post('/schools/{school}/status', [SuperAdminSchoolController::class, 'updateStatus'])
            ->middleware('scope.checked')
            ->name('schools.status');

        Route::post('/schools/{school}/admins', [SuperAdminSchoolAdminController::class, 'store'])
            ->middleware('scope.checked')
            ->name('schools.admins.store');

        Route::get('/audit-logs', [SuperAdminAuditLogController::class, 'index'])->name('audit-logs.index');
    });

/**
 * Parent web dashboard — 16-schoolos-decisions-register.md (D-register,
 * confirmed): "build it, scoped to attendance + announcements + profile
 * only in its first version." children.show is profile-only for now —
 * see DashboardController's doc comment for why.
 *
 * 'role:parent' gates who can reach this group at all (EnsureUserHasRole);
 * ScopeService inside DashboardController governs which records within
 * that role's reach a given request may touch. children.show additionally
 * carries 'scope.checked' — see DashboardController and MarkScopeChecked's
 * doc comments, and Phase1TestGateTest's route-table lint (F22, row 8),
 * which fails the build if a single-record-by-ID route in this file is
 * missing that marker.
 *
 * timetable/lesson-plans/assignments/exams (this pass's addition): the
 * read-only counterpart to teacher.timetable/lesson-plans/assignments/
 * exams above — see TeacherPortal\LessonPlanController's doc comment for
 * the shape shared across all four generic Phase 4 resources and all
 * three portals. A linked parent sees their linked children's
 * class_section(s)' rows via ScopeService::parentRelationshipScope()'s
 * class_section_id branch — no controller-level filtering beyond what
 * ResolvesScopedAcademicResource's tenantScope()->relationshipScope()
 * intersection already provides. *.index is id-less; *.show resolves one
 * row by route parameter and carries 'scope.checked', 404-not-403 on an
 * unlinked parent or a linked parent whose child isn't in that row's
 * class_section, same discipline as children.show above. No store()
 * routes — parents never author content for these four resources.
 */
Route::middleware(['auth', 'role:parent'])
    ->prefix('parent')
    ->name('parent.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        /*
         * Design ref: SchoolOS Account Creation & Onboarding plan, §4
         * (Parent onboarding). No route parameter (creates a request, it
         * doesn't resolve one by id) — exempt from row 8's lint the same
         * way every other id-less store() route in this file is.
         */
        Route::post('/child-link-requests', [ChildLinkRequestController::class, 'store'])
            ->name('child-link-requests.store');

        Route::get('/children/{student}', [DashboardController::class, 'showChild'])
            ->middleware('scope.checked')
            ->name('children.show');

        Route::get('/children/{student}/attendance', [DashboardController::class, 'childAttendance'])
            ->middleware('scope.checked')
            ->name('children.attendance');

        Route::get('/timetable', [ParentTimetableController::class, 'index'])->name('timetable.index');

        Route::get('/timetable/{timetableSlot}', [ParentTimetableController::class, 'show'])
            ->middleware('scope.checked')
            ->name('timetable.show');

        Route::get('/lesson-plans', [ParentLessonPlanController::class, 'index'])->name('lesson-plans.index');

        Route::get('/lesson-plans/{lessonPlan}', [ParentLessonPlanController::class, 'show'])
            ->middleware('scope.checked')
            ->name('lesson-plans.show');

        Route::get('/assignments', [ParentAssignmentController::class, 'index'])->name('assignments.index');

        Route::get('/assignments/{assignment}', [ParentAssignmentController::class, 'show'])
            ->middleware('scope.checked')
            ->name('assignments.show');

        Route::get('/exams', [ParentExamController::class, 'index'])->name('exams.index');

        Route::get('/exams/{exam}', [ParentExamController::class, 'show'])
            ->middleware('scope.checked')
            ->name('exams.show');

        Route::get('/exams/{exam}/results/{student}/download', [ParentExamController::class, 'download'])
            ->middleware('scope.checked')
            ->name('exams.results.download');

        /*
         * Promotion (this pass's addition, track 7b) — read-only, same
         * index()/show() shape as exams above.
         */
        Route::get('/promotions', [ParentPromotionController::class, 'index'])->name('promotions.index');

        Route::get('/promotions/{promotion}', [ParentPromotionController::class, 'show'])
            ->middleware('scope.checked')
            ->name('promotions.show');

        /*
         * Communication surface (this pass's addition, closing
         * 18-schoolos-communication-domain-map.md §8's "wire routes"
         * item): announcements/notifications/feedback, the read-only
         * counterpart to teacher.announcements above plus this portal's
         * own notification inbox and feedback-authoring surface — see
         * ParentPortal\AnnouncementController/FeedbackController's own
         * doc comments for the shape shared across portals. index routes
         * are id-less; show/markRead/feedback.show resolve one row by
         * route parameter and carry 'scope.checked', 404-not-403 on a
         * miss, same discipline as every other by-ID route in this file.
         * notifications.index carries no parameter at all — see
         * HasNotificationInbox's doc comment for why it's exempt from
         * that requirement by construction, not by omission.
         */
        Route::get('/announcements', [ParentAnnouncementController::class, 'index'])->name('announcements.index');

        Route::get('/announcements/{announcement}', [ParentAnnouncementController::class, 'show'])
            ->middleware('scope.checked')
            ->name('announcements.show');

        Route::post('/announcements/{announcement}/read', [ParentAnnouncementController::class, 'markRead'])
            ->middleware('scope.checked')
            ->name('announcements.read');

        Route::get('/notifications', [ParentNotificationController::class, 'index'])->name('notifications.index');

        Route::get('/feedback', [ParentFeedbackController::class, 'index'])->name('feedback.index');

        Route::get('/feedback/{feedback}', [ParentFeedbackController::class, 'show'])
            ->middleware('scope.checked')
            ->name('feedback.show');

        Route::post('/feedback', [ParentFeedbackController::class, 'store'])->name('feedback.store');

        /*
         * School Calendar (20-phase-6-8-execution-prompt.md §1, track
         * 6a) — read-only counterpart to admin.calendar-events below.
         * index is id-less; show resolves one row by route parameter and
         * carries 'scope.checked', 404-not-403 on a wrong-tenant id or an
         * in-tenant event with visible_to_parents = false, same
         * discipline as every other by-ID route in this file.
         */
        Route::get('/calendar-events', [ParentCalendarEventController::class, 'index'])
            ->name('calendar-events.index');

        Route::get('/calendar-events/{calendarEvent}', [ParentCalendarEventController::class, 'show'])
            ->middleware('scope.checked')
            ->name('calendar-events.show');

        /*
         * Scheme of Work / E-Textbooks (this pass's addition, closing
         * 19-discovery-hierarchy-gap-closure-plan.md §6's "wire routes"
         * item) — see TeacherPortal's own block above for the shared
         * read-only index()/show() shape; no store() routes — parents
         * never author content for either resource. markRead is added
         * here (unlike TeacherPortal) per 19 §6's own framing of
         * read-tracking as a student/parent-facing mechanism — same
         * shape as announcements.read above.
         */
        Route::get('/scheme-of-work', [ParentSchemeOfWorkController::class, 'index'])->name('scheme-of-work.index');

        Route::get('/scheme-of-work/{schemeOfWork}', [ParentSchemeOfWorkController::class, 'show'])
            ->middleware('scope.checked')
            ->name('scheme-of-work.show');

        Route::post('/scheme-of-work/{schemeOfWork}/read', [ParentSchemeOfWorkController::class, 'markRead'])
            ->middleware('scope.checked')
            ->name('scheme-of-work.read');

        Route::get('/learning-materials', [ParentLearningMaterialController::class, 'index'])->name('learning-materials.index');

        Route::get('/learning-materials/{learningMaterial}', [ParentLearningMaterialController::class, 'show'])
            ->middleware('scope.checked')
            ->name('learning-materials.show');

        Route::post('/learning-materials/{learningMaterial}/read', [ParentLearningMaterialController::class, 'markRead'])
            ->middleware('scope.checked')
            ->name('learning-materials.read');

        /*
         * Fee/Payment Portal (20-phase-6-8-execution-prompt.md §5,
         * track 6d, discovery §11) — the read/pay half of this track's
         * three surfaces (21 §1). fees.index is id-less; fees.show
         * resolves one fee_assessments row by route parameter and
         * carries 'scope.checked', 404-not-403 on another family's
         * assessment, same discipline as every other by-ID route in
         * this file. fees.payments.store is a **write**, not just a
         * read — ParentPortal\PaymentController's own doc comment
         * calls this out as the one route in this whole track where a
         * scope gap is strictly worse than a read-only IDOR (a parent
         * could pay down, or upload a fraudulent receipt against,
         * another family's balance), so it carries 'scope.checked' too,
         * per 22 §6's write-path obligation.
         */
        Route::get('/fees', [ParentFeeController::class, 'index'])->name('fees.index');

        Route::get('/fees/{feeAssessment}', [ParentFeeController::class, 'show'])
            ->middleware('scope.checked')
            ->name('fees.show');

        Route::post('/fees/{feeAssessment}/payments', [ParentPaymentController::class, 'store'])
            ->middleware('scope.checked')
            ->name('fees.payments.store');
    });

/**
 * Admin write paths for the two gaps this session closed: class_sections
 * had no way to be created outside a test fixture, and
 * class_teacher_assignments had no write path at all (see
 * ClassSectionRepository::create() and ClassTeacherAssignmentRepository's
 * own doc comments). Also wires up EnrollmentRepository and
 * ParentLinkRepository's first controllers/routes — both repositories
 * predate any route calling them, the same "built and tested, not yet
 * wired to a controller" state ClassSectionRepository was in before this.
 *
 * A later session closed the remaining Phase 2 gap: academic_years,
 * standards, and sections had no write path at all — not even a
 * partially-wired one — so admin.dashboard plus
 * academic-years.store/standards.store/sections.store were added below.
 * dashboard is the first admin.* GET route; before it, school_admin had
 * nowhere to land after login (SessionController::dashboardPathFor() had
 * no branch for it) and no view could link to any of the POST routes in
 * this group. See Admin\DashboardController's doc comment.
 *
 * A third session added subjects.store — Phase 4's first slice per
 * 15-academic-domain-map.md §2 ("CRUD only, admin-gated, lower risk"),
 * deliberately not Exams/Marks/Timetable, whose §1 core-vs-addon
 * decision is still open. Closes the same shape of gap as
 * academic-years/standards/sections: subjects had no write path even
 * though ClassTeacherAssignmentController already validates subject_id
 * against this table.
 *
 * Gated to 'role:school_admin' specifically, not any tenant-scoped role
 * in general — a super_admin (school_id === null) has no single school
 * these actions could unambiguously apply to, and Ground Rule 0 forbids
 * taking that school_id from the request instead. Extending this group
 * to super_admin would need an explicit target-school mechanism first,
 * not just a wider role list.
 *
 * Every route below that resolves an existing record by a route
 * parameter ({classSection}, {parentLink}) carries 'scope.checked' and
 * resolves that record through ScopeService::tenantScope() before doing
 * anything else with it — store()-only routes (no existing record to
 * resolve) don't need it and are exempt from row 8's lint by construction
 * (no route parameter). dashboard is likewise id-less (a listing, not a
 * single-record resolution), so it's exempt for the same reason
 * parent.dashboard/teacher.dashboard/student.dashboard are.
 * relationshipScope() is not additionally called anywhere in this group:
 * every action here is performed by 'school_admin', and ScopeService's
 * own doc comment marks that role as tenant-scope-only for Phase 1
 * (relationshipScope()'s default branch).
 */
Route::middleware(['auth', 'role:school_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/school-profile', [SchoolProfileController::class, 'edit'])
            ->name('school-profile.edit');

        Route::put('/school-profile', [SchoolProfileController::class, 'update'])
            ->name('school-profile.update');

        /*
         * Setup Wizard (SchoolOS Account Creation & Onboarding plan, §1).
         * index has no route parameter (id-less, like 'dashboard' above).
         * advance/complete mutate the acting admin's own school resolved
         * from $request->user() — no route parameter either, matching
         * every other id-less write action's exemption from row 8's lint
         * (see SetupWizardController's own doc comment).
         */
        Route::get('/setup', [SetupWizardController::class, 'index'])->name('setup.index');

        Route::post('/setup/advance', [SetupWizardController::class, 'advance'])->name('setup.advance');

        Route::post('/setup/complete', [SetupWizardController::class, 'complete'])->name('setup.complete');

        Route::post('/academic-years', [AcademicYearController::class, 'store'])
            ->name('academic-years.store');

        Route::post('/standards', [StandardController::class, 'store'])
            ->name('standards.store');

        Route::post('/sections', [SectionController::class, 'store'])
            ->name('sections.store');

        Route::post('/subjects', [SubjectController::class, 'store'])
            ->name('subjects.store');

        Route::post('/class-sections', [ClassSectionController::class, 'store'])
            ->name('class-sections.store');

        Route::post('/class-sections/{classSection}/teacher', [ClassSectionController::class, 'assignTeacher'])
            ->middleware('scope.checked')
            ->name('class-sections.assign-teacher');

        Route::post(
            '/class-sections/{classSection}/teacher-assignments',
            [ClassTeacherAssignmentController::class, 'store']
        )
            ->middleware('scope.checked')
            ->name('class-sections.teacher-assignments.store');

        Route::post('/enrollments', [EnrollmentController::class, 'store'])
            ->name('enrollments.store');

        /*
         * Design ref: SchoolOS Account Creation & Onboarding plan, §5
         * (Student onboarding). students.store creates a new row (no
         * route parameter), same exemption as enrollments.store above.
         * students.invite resolves one existing student User by route
         * parameter, so it carries 'scope.checked'.
         */
        Route::post('/students', [AdminStudentController::class, 'store'])
            ->name('students.store');

        Route::post('/students/{student}/invite', [AdminStudentController::class, 'invite'])
            ->middleware('scope.checked')
            ->name('students.invite');

        Route::post('/staff', [AdminStaffController::class, 'store'])
            ->name('staff.store');

        /*
         * Rich Teacher/Staff Enrollment And Profiles
         * (26-discovery-hierarchy-status.md) — read-only oversight of a
         * staff member's own self-authored profile. Both routes resolve
         * a record by route parameter, hence 'scope.checked'.
         */
        Route::get('/staff/{user}/profile', [AdminStaffProfileController::class, 'show'])
            ->middleware('scope.checked')
            ->name('staff.profile.show');

        Route::get('/staff/{user}/profile/documents/{document}/download', [AdminStaffProfileController::class, 'downloadDocument'])
            ->middleware('scope.checked')
            ->name('staff.profile.documents.download');

        /*
         * Timetable Management module's delegation control: the one
         * write action a school_admin has on an otherwise
         * self-authored staff profile (see
         * StaffProfileController::updatePermissions()'s own doc
         * comment for why this is scoped narrowly rather than
         * softening the "read-only oversight" rule above). Still
         * inside the role:school_admin group — only the school_admin
         * grants or revokes the delegation itself, matching
         * EnsureCanManageTimetable's doc comment ("school_admin owns
         * the module by default"). 'scope.checked' applies for the
         * same reason as the two routes above: {user} is an existing
         * record resolved by route parameter.
         */
        Route::put('/staff/{user}/permissions', [AdminStaffProfileController::class, 'updatePermissions'])
            ->middleware('scope.checked')
            ->name('staff.profile.permissions.update');

        Route::post('/parents', [ParentEnrollmentController::class, 'store'])
            ->name('parents.store');

        /*
         * Admission/Enrollment (19-discovery-hierarchy-gap-closure-plan.md
         * §10, discovery doc §12, track 7c). Decision ref:
         * 16-schoolos-decisions-register.md D16.
         *
         * admission-applications.index is an id-less tenant-scoped
         * listing, same shape as promotions.index above. show and every
         * decision action (mark-under-review/reject/withdraw/accept) all
         * resolve one application by route parameter and carry
         * 'scope.checked' — unlike Admin\PromotionController's body-
         * resolved actions, every one of these already has a natural
         * single-record route parameter, so there's no reason to exempt
         * them from row 8's lint.
         */
        Route::get('/admission-applications', [AdmissionApplicationController::class, 'index'])
            ->name('admission-applications.index');

        Route::get('/admission-applications/{admissionApplication}', [AdmissionApplicationController::class, 'show'])
            ->middleware('scope.checked')
            ->name('admission-applications.show');

        Route::post(
            '/admission-applications/{admissionApplication}/mark-under-review',
            [AdmissionApplicationController::class, 'markUnderReview']
        )
            ->middleware('scope.checked')
            ->name('admission-applications.mark-under-review');

        Route::post('/admission-applications/{admissionApplication}/reject', [AdmissionApplicationController::class, 'reject'])
            ->middleware('scope.checked')
            ->name('admission-applications.reject');

        Route::post('/admission-applications/{admissionApplication}/withdraw', [AdmissionApplicationController::class, 'withdraw'])
            ->middleware('scope.checked')
            ->name('admission-applications.withdraw');

        Route::post('/admission-applications/{admissionApplication}/accept', [AdmissionApplicationController::class, 'accept'])
            ->middleware('scope.checked')
            ->name('admission-applications.accept');

        Route::post('/parent-links', [ParentLinkController::class, 'store'])
            ->name('parent-links.store');

        Route::delete('/parent-links/{parentLink}', [ParentLinkController::class, 'destroy'])
            ->middleware('scope.checked')
            ->name('parent-links.destroy');

        /*
         * Design ref: SchoolOS Account Creation & Onboarding plan, §4
         * (Parent onboarding). approve/reject resolve a single
         * student_parent_links row by route parameter, same as destroy
         * above, so both carry 'scope.checked'.
         */
        Route::post('/parent-links/{parentLink}/approve', [ParentLinkController::class, 'approve'])
            ->middleware('scope.checked')
            ->name('parent-links.approve');

        Route::post('/parent-links/{parentLink}/reject', [ParentLinkController::class, 'reject'])
            ->middleware('scope.checked')
            ->name('parent-links.reject');

        /*
         * Lesson-plan approval workflow (this pass's addition) — first
         * routes calling LessonPlanRepository::approve()/reject(), which
         * predate any controller wiring them up. Both resolve an
         * existing lesson_plans row by ID, so both carry 'scope.checked'
         * — see Admin\LessonPlanController's own doc comment for why
         * this group's role check alone isn't the whole story (F30/F31).
         */
        Route::get('/lesson-plans/create', [AdminLessonPlanController::class, 'create'])
            ->name('lesson-plans.create');

        Route::post('/lesson-plans', [AdminLessonPlanController::class, 'store'])
            ->name('lesson-plans.store');

        Route::post('/lesson-plans/{lessonPlan}/approve', [AdminLessonPlanController::class, 'approve'])
            ->middleware('scope.checked')
            ->name('lesson-plans.approve');

        Route::post('/lesson-plans/{lessonPlan}/reject', [AdminLessonPlanController::class, 'reject'])
            ->middleware('scope.checked')
            ->name('lesson-plans.reject');

        /*
         * Announcement authoring (this pass's addition, closing
         * 18-schoolos-communication-domain-map.md §8's "wire routes"
         * item) — store-only, matching §2's "four portal controllers
         * (Admin store-only, Teacher index+store, Parent/Student
         * index-only)". school_admin has no dedicated index/show here
         * for the same reason it has none for lesson-plans/assignments/
         * exams/timetable above: this bundle's admin surface is
         * write-path-focused, not a full read UI (see
         * TeacherPortal\LessonPlanController's doc comment on why reads
         * weren't built as Blade views for this pass). No route
         * parameter, so exempt from row 8's lint the same way
         * enrollments.store/subjects.store above are.
         */
        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])
            ->name('announcements.store');

        /*
         * Scheme of Work / E-Textbooks authoring (this pass's addition,
         * closing 19-discovery-hierarchy-gap-closure-plan.md §6's "wire
         * routes" item) — store-only, same reasoning as
         * announcements.store above: school_admin's admin surface for
         * these two resources is write-path-focused, not a full read UI
         * (see TeacherPortal\SchemeOfWorkController's doc comment for
         * why reads are JSON, not Blade views, for this pass). No route
         * parameter on either, so both are exempt from row 8's lint the
         * same way announcements.store/subjects.store above are.
         */
        Route::post('/scheme-of-work', [AdminSchemeOfWorkController::class, 'store'])
            ->name('scheme-of-work.store');

        Route::post('/learning-materials', [AdminLearningMaterialController::class, 'store'])
            ->name('learning-materials.store');

        Route::post('/exam-components', [AdminExamComponentController::class, 'store'])
            ->name('exam-components.store');

        Route::get('/exams', [AdminExamController::class, 'index'])
            ->name('exams.index');

        Route::get('/exams/{exam}', [AdminExamController::class, 'show'])
            ->middleware('scope.checked')
            ->name('exams.show');

        Route::post('/exams/{exam}/marks/{mark}', [AdminExamController::class, 'updateMark'])
            ->middleware('scope.checked')
            ->name('exams.marks.update');

        Route::post('/exams/{exam}/marks/{mark}/review', [AdminExamController::class, 'review'])
            ->middleware('scope.checked')
            ->name('exams.marks.review');

        Route::post('/exams/{exam}/marks/{mark}/publish', [AdminExamController::class, 'publish'])
            ->middleware('scope.checked')
            ->name('exams.marks.publish');

        /*
         * Rich Report Card Fields (26-discovery-hierarchy-status.md) —
         * the proprietor-remark write path. Carries $exam by route
         * parameter (hence 'scope.checked'), same as the marks routes
         * directly above.
         */
        Route::post('/exams/{exam}/remarks/{student}', [AdminExamController::class, 'updateRemark'])
            ->middleware('scope.checked')
            ->name('exams.remarks.update');

        /*
         * School Calendar (20-phase-6-8-execution-prompt.md §1, track
         * 6a) — school_admin's CRUD surface, matching this group's
         * write-path-focused convention (see Admin\CalendarEventController's
         * own doc comment for why there's no index/show here). update/
         * destroy resolve an existing calendar_events row by route
         * parameter, so both carry 'scope.checked' per row 8's lint,
         * same discipline as class-sections.assign-teacher above.
         */
        Route::post('/calendar-events', [AdminCalendarEventController::class, 'store'])
            ->name('calendar-events.store');

        Route::put('/calendar-events/{calendarEvent}', [AdminCalendarEventController::class, 'update'])
            ->middleware('scope.checked')
            ->name('calendar-events.update');

        Route::delete('/calendar-events/{calendarEvent}', [AdminCalendarEventController::class, 'destroy'])
            ->middleware('scope.checked')
            ->name('calendar-events.destroy');

        /*
         * Backup & Data Management (19-discovery-hierarchy-gap-closure-plan.md
         * §11, 20-phase-6-8-execution-prompt.md §2). Decision ref:
         * 16-schoolos-decisions-register.md D11 — storage/retention/
         * access closed, working export logic built.
         *
         * store has no route parameter (same exemption from row 8's
         * lint as calendar-events.store above) — it only records the
         * request and dispatches the generation job.
         *
         * download resolves an existing export_jobs row by route
         * parameter, so it carries 'scope.checked' per row 8's lint,
         * same discipline as calendar-events.update/destroy above —
         * Admin\ExportJobController::download() resolves it through
         * ScopeService::tenantScope() (D11 item 3: any school_admin at
         * the school, not requester-only).
         */
        Route::post('/export-jobs', [ExportJobController::class, 'store'])
            ->name('export-jobs.store');

        Route::get('/export-jobs/{exportJob}/download', [ExportJobController::class, 'download'])
            ->middleware('scope.checked')
            ->name('export-jobs.download');

        /*
         * ID Cards + Staff Attendance (20-phase-6-8-execution-prompt.md
         * §4, track 6b). Decision ref: 16-schoolos-decisions-register.md
         * D12 (CONFIRMED — unblocks both routes below). D13 remains
         * PROPOSED, so no 'face' scan/verification route exists here —
         * scan() only ever calls StaffAttendanceRepository::checkIn()
         * with method 'qr', which D12 alone covers.
         *
         * id-cards.show resolves an existing user by route parameter,
         * so it carries 'scope.checked' per row 8's lint, same
         * discipline as calendar-events.update/export-jobs.download
         * above. staff-attendance.scan/index have no route parameter
         * (scan's identity comes from the posted QR token, not a route
         * segment; index is a tenant-scoped listing) — same exemption
         * as calendar-events.store/export-jobs.store above.
         */
        Route::get('/users/{user}/id-card', [IdCardController::class, 'show'])
            ->middleware('scope.checked')
            ->name('users.id-card');

        Route::get('/staff-attendance', [AdminStaffAttendanceController::class, 'index'])
            ->name('staff-attendance.index');

        Route::post('/staff-attendance/scan', [AdminStaffAttendanceController::class, 'scan'])
            ->name('staff-attendance.scan');

        /*
         * Fees, Payments, Finance (20-phase-6-8-execution-prompt.md §5,
         * track 6d). Design ref: 21-schoolos-finance-architecture.md,
         * 22-schoolos-finance-schema.md. Decision refs:
         * 16-schoolos-decisions-register.md D11-D13. First routes for
         * this track's Admin controllers/repositories, which predate
         * any route calling them — the same "built and tested, not yet
         * wired to a controller" state ClassSectionRepository was in
         * before Phase 2 (this file's own earlier comment on that
         * group). Given a real Blade UI per 21 §1's "School Finance /
         * Admin Dashboard" surface, every controller in this block
         * returns a View/RedirectResponse, not JSON — see each
         * controller's own doc comment for why this differs from the
         * announcements.store/scheme-of-work.store JSON-only
         * convention elsewhere in this group.
         *
         * finance.dashboard, fee-assessments.index, expenses.index are
         * id-less listings (finance.dashboard is a reporting view over
         * the acting admin's own tenant; fee-assessments.index/
         * expenses.index list-plus-create-form pages), so none needs
         * 'scope.checked'. fee-categories.store/fee-assessments.store/
         * scholarships.store/expenses.store all create a new row rather
         * than resolve one, so all four are exempt from row 8's lint
         * for the same reason enrollments.store is, per this group's
         * own doc comment above.
         *
         * fee-assessments.show/adjust and fee-assessments.payments.store
         * each resolve an existing fee_assessments row by route
         * parameter before doing anything else with it (via
         * ScopeService::tenantScope() — see each controller's own doc
         * comment), so all three carry 'scope.checked' per row 8's
         * lint, same discipline as class-sections.assign-teacher above.
         * fee-assessments.payments.store is a write, not just a read —
         * 21 §5 calls this out explicitly as the one Admin-side finance
         * route where a scope gap would be strictly worse than a
         * read-only one, the same reasoning ParentPortal's own
         * fees.payments.store route below carries.
         */
        Route::get('/finance/dashboard', [FinanceDashboardController::class, 'index'])
            ->name('finance.dashboard');

        Route::post('/fee-categories', [AdminFeeCategoryController::class, 'store'])
            ->name('fee-categories.store');

        Route::get('/fee-assessments', [AdminFeeAssessmentController::class, 'index'])
            ->name('fee-assessments.index');

        Route::get('/fee-assessments/{feeAssessment}', [AdminFeeAssessmentController::class, 'show'])
            ->middleware('scope.checked')
            ->name('fee-assessments.show');

        Route::post('/fee-assessments', [AdminFeeAssessmentController::class, 'store'])
            ->name('fee-assessments.store');

        Route::post('/fee-assessments/{feeAssessment}/adjust', [AdminFeeAssessmentController::class, 'adjust'])
            ->middleware('scope.checked')
            ->name('fee-assessments.adjust');

        Route::post('/fee-assessments/{feeAssessment}/payments', [AdminPaymentController::class, 'store'])
            ->middleware('scope.checked')
            ->name('fee-assessments.payments.store');

        Route::post('/scholarships', [AdminScholarshipController::class, 'store'])
            ->name('scholarships.store');

        Route::get('/expenses', [AdminExpenseController::class, 'index'])
            ->name('expenses.index');

        Route::post('/expenses', [AdminExpenseController::class, 'store'])
            ->name('expenses.store');

        /*
         * Student Promotion (19-discovery-hierarchy-gap-closure-plan.md
         * §8, 20-phase-6-8-execution-prompt.md §6, track 7b). Decision
         * ref: 16-schoolos-decisions-register.md D15.
         *
         * promotion-rules.store has no route parameter (upserts by
         * school/year/standard from the request body, same shape as
         * calendar-events.store), exempt from row 8's lint.
         *
         * promotions.index is an id-less tenant-scoped listing, same
         * shape as fee-assessments.index above, exempt for the same
         * reason. promotions.run-automatic/promotions.override both
         * resolve their targets from body parameters, not a route
         * parameter — see Admin\PromotionController's own doc comment
         * for why this mirrors exam-marks.store's exemption rather than
         * calendar-events.update's 'scope.checked' requirement.
         */
        Route::post('/promotion-rules', [PromotionRuleController::class, 'store'])
            ->name('promotion-rules.store');

        Route::get('/promotions', [AdminPromotionController::class, 'index'])
            ->name('promotions.index');

        Route::post('/promotions/run-automatic', [AdminPromotionController::class, 'runAutomatic'])
            ->name('promotions.run-automatic');

        Route::post('/promotions/override', [AdminPromotionController::class, 'override'])
            ->name('promotions.override');
    });

/*
 * Timetable Management module (School Admin: build/edit/delete slots,
 * conflict detection, configure periods/working days, preview and
 * publish/unpublish, delegate management to a staff member).
 *
 * Deliberately a SEPARATE group from the 'role:school_admin' admin.*
 * group above, gated to 'timetable.manage' instead — reachable by
 * school_admin OR a staff member delegated via
 * staff_profiles.can_manage_timetable (see EnsureCanManageTimetable's
 * own doc comment). Same 'admin' prefix / 'admin.' name so
 * admin.timetable.* route names and /admin/timetable/... URLs are
 * unchanged for callers that already reference them.
 *
 * Every route resolving an existing record by a route parameter
 * ({timetableSlot}) carries 'scope.checked', matching row 8's lint
 * convention in the group above. create/store/settings/publish/
 * unpublish are id-less (a form, or an upsert keyed off the actor's own
 * school_id) and are exempt for the same reason promotions.run-automatic
 * is exempt above.
 */
Route::middleware(['auth', 'timetable.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/timetable', [AdminTimetableController::class, 'index'])
            ->name('timetable.index');

        Route::get('/timetable/create', [AdminTimetableController::class, 'create'])
            ->name('timetable.create');

        Route::post('/timetable', [AdminTimetableController::class, 'store'])
            ->name('timetable.store');

        Route::get('/timetable/settings', [AdminTimetableController::class, 'settings'])
            ->name('timetable.settings');

        Route::put('/timetable/settings', [AdminTimetableController::class, 'updateSettings'])
            ->name('timetable.settings.update');

        Route::post('/timetable/publish', [AdminTimetableController::class, 'publish'])
            ->name('timetable.publish');

        Route::post('/timetable/unpublish', [AdminTimetableController::class, 'unpublish'])
            ->name('timetable.unpublish');

        Route::get('/timetable/{timetableSlot}', [AdminTimetableController::class, 'show'])
            ->middleware('scope.checked')
            ->name('timetable.show');

        Route::get('/timetable/{timetableSlot}/edit', [AdminTimetableController::class, 'edit'])
            ->middleware('scope.checked')
            ->name('timetable.edit');

        Route::put('/timetable/{timetableSlot}', [AdminTimetableController::class, 'update'])
            ->middleware('scope.checked')
            ->name('timetable.update');

        Route::delete('/timetable/{timetableSlot}', [AdminTimetableController::class, 'destroy'])
            ->middleware('scope.checked')
            ->name('timetable.destroy');
    });

/**
 * Teacher-facing GET surface for Attendance (UI vertical slice — this
 * pass's addition). dashboard and attendance.index are id-less listings,
 * same shape as ParentPortal\DashboardController::index(), so neither
 * needs 'scope.checked'. attendance.show resolves one ClassSection by
 * route parameter — same 404-not-403 resolution as attendance.store's
 * $classSection lookup (see AttendanceController's doc comment) — so it
 * carries 'scope.checked' per Phase1TestGateTest's row 8 lint, the same
 * way parent.children.show does above.
 *
 * Phase 3's first teacher-facing write path, attendance.store, is
 * unmodified from its original form: still no route parameter (it
 * marks/corrects a row, it doesn't resolve one by id), still exempt from
 * row 8's lint for that reason, the same reasoning EnrollmentController's
 * store() route documents.
 *
 * timetable/lesson-plans/assignments/exams (this pass's addition): the
 * read + teacher-create surface for the four generic Phase 4 resources —
 * see TeacherPortal\LessonPlanController's doc comment for the shape
 * shared by all four controllers behind these twelve routes (these four
 * plus their ParentPortal/StudentPortal read-only counterparts below).
 * Same index()/show()/store() shape as attendance above: *.index is
 * id-less, *.show resolves one row by route parameter and carries
 * 'scope.checked', *.store has no route parameter (it creates a new row,
 * it doesn't resolve one by id) and is exempt from row 8's lint for that
 * reason. Kebab-cased multi-word segment ('lesson-plans'), matching
 * 'class-sections'/'parent-links'/'teacher-assignments' above.
 */
Route::middleware(['auth', 'role:teacher'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {
        Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');

        Route::get('/profile', [TeacherProfileController::class, 'show'])->name('profile.show');

        Route::put('/profile', [TeacherProfileController::class, 'update'])->name('profile.update');

        /*
         * Rich Teacher/Staff Enrollment And Profiles
         * (26-discovery-hierarchy-status.md). update-details/cv/
         * documents.store/acknowledge-rules all resolve their target via
         * $request->user() alone (no route parameter), the same shape
         * 'profile.update' above already has — exempt from row 8's lint
         * for that reason. documents.destroy/download carry a route
         * parameter, so they take 'scope.checked' even though the actual
         * ownership check happens in StaffProfileRepository /
         * this controller directly, not ScopeService (see
         * destroyDocument()'s own doc comment).
         */
        Route::put('/profile/details', [TeacherProfileController::class, 'updateProfile'])
            ->name('profile.update-details');

        Route::post('/profile/cv', [TeacherProfileController::class, 'uploadCv'])
            ->name('profile.cv.store');

        Route::post('/profile/documents', [TeacherProfileController::class, 'storeDocument'])
            ->name('profile.documents.store');

        Route::delete('/profile/documents/{document}', [TeacherProfileController::class, 'destroyDocument'])
            ->middleware('scope.checked')
            ->name('profile.documents.destroy');

        Route::get('/profile/documents/{document}/download', [TeacherProfileController::class, 'downloadDocument'])
            ->middleware('scope.checked')
            ->name('profile.documents.download');

        Route::post('/profile/acknowledge-rules', [TeacherProfileController::class, 'acknowledgeRules'])
            ->name('profile.acknowledge-rules');

        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');

        Route::get('/attendance/{classSection}', [AttendanceController::class, 'show'])
            ->middleware('scope.checked')
            ->name('attendance.show');

        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

        /*
         * Subject Attendance (26-discovery-hierarchy-status.md "Subject
         * Attendance" gap closure): same index()/show()/store() shape as
         * attendance.* above, plus subject-attendance.topic — a fourth
         * write endpoint with no route parameter (it upserts the one
         * topic row for a slot+date, it doesn't resolve one by id), so it
         * is exempt from row 8's lint the same way attendance.store is.
         */
        Route::get('/subject-attendance', [SubjectAttendanceController::class, 'index'])
            ->name('subject-attendance.index');

        Route::get('/subject-attendance/{timetableSlot}', [SubjectAttendanceController::class, 'show'])
            ->middleware('scope.checked')
            ->name('subject-attendance.show');

        Route::post('/subject-attendance', [SubjectAttendanceController::class, 'store'])
            ->name('subject-attendance.store');

        Route::post('/subject-attendance/topic', [SubjectAttendanceController::class, 'topic'])
            ->name('subject-attendance.topic');

        Route::get('/timetable', [TeacherTimetableController::class, 'index'])->name('timetable.index');

        Route::get('/timetable/{timetableSlot}', [TeacherTimetableController::class, 'show'])
            ->middleware('scope.checked')
            ->name('timetable.show');

        Route::get('/lesson-plans', [TeacherLessonPlanController::class, 'index'])->name('lesson-plans.index');

        Route::get('/lesson-plans/{lessonPlan}', [TeacherLessonPlanController::class, 'show'])
            ->middleware('scope.checked')
            ->name('lesson-plans.show');

        Route::post('/lesson-plans', [TeacherLessonPlanController::class, 'store'])->name('lesson-plans.store');

        Route::get('/assignments', [TeacherAssignmentController::class, 'index'])->name('assignments.index');

        Route::get('/assignments/{assignment}', [TeacherAssignmentController::class, 'show'])
            ->middleware('scope.checked')
            ->name('assignments.show');

        Route::post('/assignments', [TeacherAssignmentController::class, 'store'])->name('assignments.store');

        /*
         * Grading write path (this pass's addition) — resolves an
         * existing assignment_submissions row by ID, so carries
         * 'scope.checked'. See AssignmentSubmissionRepository::grade()'s
         * own doc comment for the F29 fix it enforces.
         */
        Route::post('/submissions/{submission}/grade', [TeacherAssignmentController::class, 'grade'])
            ->middleware('scope.checked')
            ->name('submissions.grade');

        Route::get('/exams', [TeacherExamController::class, 'index'])->name('exams.index');

        Route::get('/exams/{exam}', [TeacherExamController::class, 'show'])
            ->middleware('scope.checked')
            ->name('exams.show');

        Route::post('/exams', [TeacherExamController::class, 'store'])->name('exams.store');

        /*
         * Marks-entry write path (this pass's addition) — no route
         * parameter (upserts by exam_id + student_id from the request
         * body), exempt from row 8's lint for the same reason
         * exams.store above is. See ExamMarkRepository::record()'s own
         * doc comment for the F29 fix it enforces.
         */
        Route::post('/exam-marks', [TeacherExamController::class, 'recordMark'])->name('exam-marks.store');

        /*
         * Rich Report Card Fields (26-discovery-hierarchy-status.md) —
         * the class-teacher-remark write path, same no-route-parameter
         * shape as exam-marks.store above (upserts by exam_id +
         * student_id from the request body).
         */
        Route::post('/exam-remarks', [TeacherExamController::class, 'recordRemark'])->name('exam-remarks.store');

        /*
         * Communication surface (this pass's addition, closing
         * 18-schoolos-communication-domain-map.md §8's "wire routes"
         * item) — see ParentPortal's own communication block above for
         * the shared show()/markRead()/notifications.index shape.
         * announcements.store is class_section-only and delegates the
         * "must be assigned to this section" gate to
         * AnnouncementRepository::create() (see
         * TeacherPortal\AnnouncementController's doc comment), same
         * TeacherNotAssignedToSectionFailure-becomes-404 discipline as
         * lesson-plans.store/assignments.store/exams.store above. No
         * feedback routes here — 18 §4/§8 leaves teacher-side feedback
         * reading undecided, not built by this pass.
         */
        Route::get('/announcements', [TeacherAnnouncementController::class, 'index'])->name('announcements.index');

        Route::get('/announcements/{announcement}', [TeacherAnnouncementController::class, 'show'])
            ->middleware('scope.checked')
            ->name('announcements.show');

        Route::post('/announcements/{announcement}/read', [TeacherAnnouncementController::class, 'markRead'])
            ->middleware('scope.checked')
            ->name('announcements.read');

        Route::post('/announcements', [TeacherAnnouncementController::class, 'store'])->name('announcements.store');

        Route::get('/notifications', [TeacherNotificationController::class, 'index'])->name('notifications.index');

        Route::get('/calendar-events', [TeacherCalendarEventController::class, 'index'])
            ->name('calendar-events.index');

        Route::get('/calendar-events/{calendarEvent}', [TeacherCalendarEventController::class, 'show'])
            ->middleware('scope.checked')
            ->name('calendar-events.show');

        /*
         * Scheme of Work / E-Textbooks (this pass's addition, closing
         * 19-discovery-hierarchy-gap-closure-plan.md §6's "wire routes"
         * item) — read-only for this portal on both resources:
         * scheme_of_work has no teacher-authoring branch (see
         * TeacherPortal\SchemeOfWorkController's doc comment) and
         * learning_materials is school_admin-only per
         * LearningMaterialRepository::create()'s own role check. No
         * markRead here — 19 §6 frames read-tracking as a
         * student/parent-facing mechanism, so it isn't added to this
         * portal for either resource (see
         * StudentPortal\SchemeOfWorkController's doc comment). *.index
         * is id-less; *.show resolves one row by route parameter and
         * carries 'scope.checked', 404-not-403 on a miss, same
         * discipline as every other by-ID route in this file.
         */
        Route::get('/scheme-of-work', [TeacherSchemeOfWorkController::class, 'index'])->name('scheme-of-work.index');

        Route::get('/scheme-of-work/{schemeOfWork}', [TeacherSchemeOfWorkController::class, 'show'])
            ->middleware('scope.checked')
            ->name('scheme-of-work.show');

        Route::get('/learning-materials', [TeacherLearningMaterialController::class, 'index'])->name('learning-materials.index');

        Route::get('/learning-materials/{learningMaterial}', [TeacherLearningMaterialController::class, 'show'])
            ->middleware('scope.checked')
            ->name('learning-materials.show');
    });

/**
 * Student web portal — this session's own task doc: "build the student
 * portal, scoped identically to what the parent portal's child-profile/
 * child-attendance pages already show about a linked child, except a
 * student sees only their own record, with no route parameter/lookup step
 * at all." See StudentPortal\DashboardController's doc comment.
 *
 * Both routes are id-less listings of the acting student's own data, the
 * same shape as teacher.dashboard and teacher.attendance.index above —
 * neither of those carries 'scope.checked' either, and for the same
 * reason: that middleware (and Phase1TestGateTest's row 8 route-table
 * lint) only applies to routes that resolve an existing record by a route
 * parameter. Neither route here has one — a student's own id is never
 * taken from the request at all, so there is no id to check.
 *
 * timetable/lesson-plans/assignments/exams (this pass's addition): the
 * read-only counterpart to teacher.timetable/lesson-plans/assignments/
 * exams and parent.timetable/lesson-plans/assignments/exams above — see
 * TeacherPortal\LessonPlanController's doc comment for the shape shared
 * across all four generic Phase 4 resources and all three portals. A
 * student sees their own class_section's rows via
 * ScopeService::studentRelationshipScope()'s class_section_id branch
 * (resolved via the student's own current StudentEnrollment row(s)).
 * *.index is id-less; *.show resolves one row by route parameter and
 * carries 'scope.checked', 404-not-403 on an unrelated student, per
 * Phase1TestGateTest's row 8 lint. No store() routes — students never
 * author content for these four resources.
 */
Route::middleware(['auth', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');

        Route::get('/attendance', [StudentDashboardController::class, 'attendance'])->name('attendance');

        Route::get('/timetable', [StudentTimetableController::class, 'index'])->name('timetable.index');

        Route::get('/timetable/{timetableSlot}', [StudentTimetableController::class, 'show'])
            ->middleware('scope.checked')
            ->name('timetable.show');

        Route::get('/lesson-plans', [StudentLessonPlanController::class, 'index'])->name('lesson-plans.index');

        Route::get('/lesson-plans/{lessonPlan}', [StudentLessonPlanController::class, 'show'])
            ->middleware('scope.checked')
            ->name('lesson-plans.show');

        Route::get('/assignments', [StudentAssignmentController::class, 'index'])->name('assignments.index');

        Route::get('/assignments/{assignment}', [StudentAssignmentController::class, 'show'])
            ->middleware('scope.checked')
            ->name('assignments.show');

        /*
         * Submission write path (this pass's addition) — resolves an
         * existing assignments row by ID before writing the student's
         * own submission against it, so carries 'scope.checked'. See
         * AssignmentSubmissionRepository::submit()'s own doc comment.
         */
        Route::post('/assignments/{assignment}/submissions', [StudentAssignmentController::class, 'submit'])
            ->middleware('scope.checked')
            ->name('assignments.submissions.store');

        Route::get('/exams', [StudentExamController::class, 'index'])->name('exams.index');

        Route::get('/exams/{exam}', [StudentExamController::class, 'show'])
            ->middleware('scope.checked')
            ->name('exams.show');

        Route::get('/exams/{exam}/result/download', [StudentExamController::class, 'download'])
            ->middleware('scope.checked')
            ->name('exams.result.download');

        /*
         * Promotion (this pass's addition, track 7b) — read-only, same
         * index()/show() shape as exams above.
         */
        Route::get('/promotions', [StudentPromotionController::class, 'index'])->name('promotions.index');

        Route::get('/promotions/{promotion}', [StudentPromotionController::class, 'show'])
            ->middleware('scope.checked')
            ->name('promotions.show');

        /*
         * Communication surface (this pass's addition, closing
         * 18-schoolos-communication-domain-map.md §8's "wire routes"
         * item) — see ParentPortal's own communication block above for
         * the shared announcements/notifications/feedback shape; a
         * student's feedback.store is self-only (student_id must equal
         * their own id), enforced by FeedbackRepository::create(), not
         * by anything at this route-table level.
         */
        Route::get('/announcements', [StudentAnnouncementController::class, 'index'])->name('announcements.index');

        Route::get('/announcements/{announcement}', [StudentAnnouncementController::class, 'show'])
            ->middleware('scope.checked')
            ->name('announcements.show');

        Route::post('/announcements/{announcement}/read', [StudentAnnouncementController::class, 'markRead'])
            ->middleware('scope.checked')
            ->name('announcements.read');

        Route::get('/notifications', [StudentNotificationController::class, 'index'])->name('notifications.index');

        Route::get('/feedback', [StudentFeedbackController::class, 'index'])->name('feedback.index');

        Route::get('/feedback/{feedback}', [StudentFeedbackController::class, 'show'])
            ->middleware('scope.checked')
            ->name('feedback.show');

        Route::post('/feedback', [StudentFeedbackController::class, 'store'])->name('feedback.store');

        /*
         * School Calendar (20-phase-6-8-execution-prompt.md §1, track
         * 6a) — see ParentPortal's own calendar-events block above for
         * the shared read-only shape.
         */
        Route::get('/calendar-events', [StudentCalendarEventController::class, 'index'])
            ->name('calendar-events.index');

        Route::get('/calendar-events/{calendarEvent}', [StudentCalendarEventController::class, 'show'])
            ->middleware('scope.checked')
            ->name('calendar-events.show');

        /*
         * Scheme of Work / E-Textbooks (this pass's addition, closing
         * 19-discovery-hierarchy-gap-closure-plan.md §6's "wire routes"
         * item) — see ParentPortal's own block above for the shared
         * read-only index()/show()/markRead() shape; no store() routes —
         * students never author content for either resource.
         */
        Route::get('/scheme-of-work', [StudentSchemeOfWorkController::class, 'index'])->name('scheme-of-work.index');

        Route::get('/scheme-of-work/{schemeOfWork}', [StudentSchemeOfWorkController::class, 'show'])
            ->middleware('scope.checked')
            ->name('scheme-of-work.show');

        Route::post('/scheme-of-work/{schemeOfWork}/read', [StudentSchemeOfWorkController::class, 'markRead'])
            ->middleware('scope.checked')
            ->name('scheme-of-work.read');

        Route::get('/learning-materials', [StudentLearningMaterialController::class, 'index'])->name('learning-materials.index');

        Route::get('/learning-materials/{learningMaterial}', [StudentLearningMaterialController::class, 'show'])
            ->middleware('scope.checked')
            ->name('learning-materials.show');

        Route::post('/learning-materials/{learningMaterial}/read', [StudentLearningMaterialController::class, 'markRead'])
            ->middleware('scope.checked')
            ->name('learning-materials.read');

        /*
         * Fee Portal, read-only (20-phase-6-8-execution-prompt.md §5,
         * track 6d) — see StudentPortal\FeeController's own doc comment
         * for why this exists even though discovery §11 only names a
         * *Parent* Fee Portal. fees.index is id-less; fees.show
         * resolves one fee_assessments row by route parameter and
         * carries 'scope.checked', 404-not-403 on a miss, same
         * discipline as every other by-ID route in this file. No
         * fees.payments.store here — students never initiate payments,
         * per that controller's own doc comment.
         */
        Route::get('/fees', [StudentFeeController::class, 'index'])->name('fees.index');

        Route::get('/fees/{feeAssessment}', [StudentFeeController::class, 'show'])
            ->middleware('scope.checked')
            ->name('fees.show');
    });

/*
 * Staff self-check-in (20-phase-6-8-execution-prompt.md §4, track 6b).
 * Deliberately its own role group, not folded into 'role:school_admin'
 * or 'role:teacher' above — StaffAttendanceRepository::STAFF_ROLE_KEYS
 * spans six roles (school_admin, teacher, accountant, librarian,
 * receptionist, staff), none of which is more "home" for this route
 * than another, and EnsureUserHasRole's variadic signature supports a
 * multi-role group directly rather than duplicating this one route
 * under six separate groups. The role list here and
 * StaffAttendanceRepository::STAFF_ROLE_KEYS must be kept in sync by
 * inspection — both exist specifically so "can reach the route" and
 * "is accepted by checkIn()" are checked against the same six keys,
 * per EnsureUserHasRole's own doc comment on that division of
 * responsibility.
 *
 * No route parameter (identity comes from $request->user(), never a
 * route segment — see CheckInController's own doc comment), so this is
 * exempt from row 8's 'scope.checked' lint the same way every other
 * id-less store() route in this file is.
 */
Route::middleware(['auth', 'role:school_admin,teacher,accountant,librarian,receptionist,staff'])
    ->prefix('staff-attendance')
    ->name('staff-attendance.')
    ->group(function () {
        Route::post('/check-in', [StaffCheckInController::class, 'store'])->name('check-in');
    });

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §3 (Staff
 * onboarding)
 *
 * Generic Staff Portal — 'accountant','librarian','receptionist','staff'
 * only (not 'teacher'/'school_admin', both of which already have their
 * own full dashboards; not 'school_admin', which manages these accounts
 * rather than using this one). See StaffPortal\DashboardController's own
 * doc comment for why this stays minimal. No route parameter (id-less,
 * same shape as every other role's *.dashboard route), so no
 * 'scope.checked' needed.
 */
Route::middleware(['auth', 'role:accountant,librarian,receptionist,staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/dashboard', [StaffPortalDashboardController::class, 'index'])->name('dashboard');
    });
