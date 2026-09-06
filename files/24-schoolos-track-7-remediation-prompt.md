# 24 — Track 7 Remediation Prompt

**Purpose:** Working prompt/brief for whoever (engineer, PM, or AI assistant) picks up the flaws found while starting Track 7a → 7b → 7c. Paste the relevant section below into a session when starting that piece of work. Written because the flaws below were found by *checking*, not by re-reading a doc comment and trusting it — the whole point of `19` §0 / `01` §5's binding rule, restated here so this remediation doesn't repeat the failure it's fixing.

**Ground rule, unchanged from `20` §0:** No item below closes on a doc comment or a chat claim that the code looks correct. It closes on a real check — a real `php artisan test` run for anything code-level, a real read of the file that's supposed to exist for anything doc-level.

---

## 0. Before starting anything

Read, in order:
1. `19-discovery-hierarchy-gap-closure-plan.md` §7/§8/§10 — the Exams/Promotion/Admission gaps this track covers.
2. `20-phase-6-8-execution-prompt.md` §6 — the original Track 7a→7b→7c prompt this document is remediating.
3. `23-schoolos-exam-domain-map.md` — written this pass, retroactively, after discovering it was cited as existing but had never actually been created. Read it critically, not as settled fact — see §1 below.
4. `16-schoolos-decisions-register.md` D15 — cites `23-schoolos-exam-domain-map.md §2/§4/§5` as its source. That citation is now true, but wasn't when D15 was recorded — see §1.

---

## 1. Verify `23-schoolos-exam-domain-map.md` against the actual codebase, not against this document's own prose

**What happened:** `16` D15 and `app/Repositories/PromotionRepository.php`/`tests/Feature/Phase7bTestGateTest.php` all cite `23-schoolos-exam-domain-map.md` as an existing source document. It did not exist anywhere in `files.zip` — 7b was built and closed against a domain-map pass that was never written down. `23-schoolos-exam-domain-map.md` was created this pass to make those citations true, using direct evidence gathered from `database/migrations/2026_08_27_000004_create_exams_marks_tables.php`, `app/Models/Exam.php`, `app/Models/ExamMark.php`, and `app/Http/Controllers/TeacherPortal/ExamController.php`.

**Prompt:**
> Re-derive `23-schoolos-exam-domain-map.md`'s findings independently against the current `schoolos.zip` — do not simply accept the document because it now exists. Confirm: (a) `exam_marks` still has no delivery-mode column and no CBT-specific fields, (b) `TeacherPortal\ExamController::recordMark()` is still the only write path to `exam_marks`, (c) no proprietor-remark, PDF-export, or attendance-aggregate fields have been added since. If any of these have changed, `23`'s §2–§4 findings are stale and need a real rewrite, not a patch. Once confirmed current, add a line to `16-schoolos-decisions-register.md` recording that `23` now exists and was independently re-verified, not just authored — this is the specific gap that let a false citation stand uncaught for a full phase.

**Do not treat `23`'s existence as closing the underlying product-scope question.** `23` §5 is explicit: whether to build CBT-mode delivery, PDF result generation, and the proprietor-remark/attendance-aggregate fields remains open. Nothing in this remediation pass should be read as resolving that — it only closes the narrower "is manual/paper-exam upload already supported" question 7b actually depended on.

---

## 2. Get a real test run on Track 7b (Promotion) before treating the file-casing fix as verified

**What happened:** Every promotion-related class file in `app/`, plus one test file, was saved with a filename that didn't match its class name in case, one with a stray trailing space before `.php`, one with a misspelling — the identical failure mode `16` D14 already documented for the finance track ("only ever ran on the case-insensitive Windows dev environment they were authored on"). Specifically, before this pass:

| Old filename | Class inside | Fixed to |
|---|---|---|
| `app/Models/Promotionrule.php` | `PromotionRule` | `PromotionRule.php` |
| `app/Http/Controllers/StudentPortal/Promotioncontroller.php` | `PromotionController` | `PromotionController.php` |
| `app/Http/Controllers/Admin/Promotionrulecontroller.php` | `PromotionRuleController` | `PromotionRuleController.php` |
| `app/Http/Controllers/Admin/Promotioncontroller .php` (trailing space) | `PromotionController` | `PromotionController.php` |
| `app/Http/Controllers/ParentPortal/Promotioncontrolle.php` (misspelled) | `PromotionController` | `PromotionController.php` |
| `app/Repositories/Promotionrepository.php` | `PromotionRepository` | `PromotionRepository.php` |
| `app/Repositories/Promotionrulerepository.php` | `PromotionRuleRepository` | `PromotionRuleRepository.php` |
| `app/Exceptions/Promotion/Missingpromotionreasonfailure.php` | `MissingPromotionReasonFailure` | `MissingPromotionReasonFailure.php` |
| `tests/Feature/Phase7btestgatetest.php` | `Phase7bTestGateTest` | `Phase7bTestGateTest.php` |

Two Blade views were also missing outright (not just miscased): `resources/views/parent/promotions/show.blade.php` and `resources/views/student/promotions/index.blade.php` didn't exist — only the other portal's counterpart did (`Index.blade.php` capitalized, and a `Show.blade.php` capitalized, in the wrong folders respectively). Both were authored fresh this pass, adapted from the sibling view that did exist, and the two capitalized ones were renamed to lowercase to match the `view('...promotions.index')` / `view('...promotions.show')` calls in the controllers.

All of this was verified by static cross-reference only (`grep` for stale references, matching `use` statements to file paths, matching `view()` calls to actual filenames) — **no PHP runtime was available in the environment this remediation happened in, so `php artisan test` was never actually run.**

**Prompt:**
> Run `php artisan test --filter=Phase7bTestGateTest` against a real PHP/Composer environment. If it fails to even boot (class-not-found), the rename above missed something — grep the whole repo for any remaining reference to the old filenames' implied class-loading path (`composer dump-autoload -o` and check its output for warnings first). If it boots but a specific test fails, that's a logic bug this remediation didn't introduce but also didn't catch by static reading alone — do not assume the static fix was sufficient. Once green, run the full suite (`php artisan test`, not just this filter) the same way `16` D14 did for the finance track, and only then add a `16`-style decision-register entry stating Track 7b closed on a real test run — do not word it as "built and verified" if the verification was static-only.

**Also worth a quick check while in there:** confirm no other track (6a/6c/6d/8) has the same case-mismatch pattern that just happened to not get caught yet — this is now the *second* time this exact defect shape has shown up (finance track, now promotion track), which suggests whatever authoring process produces these files is doing it systematically, not as a one-off. Worth finding and fixing at the source rather than catching it phase-by-phase.

---

## 3. Build Track 7c — Admission/Enrollment workflow (not started)

**Current state:** Nothing exists — no `admission_applications` table, model, repository, controller, or route anywhere in `schoolos.zip`. This is the one item from the original Track 7 scope that got flagged as blocked and never actually picked back up.

**Blocking decision, now resolved by circumstance:** `19` §10 and `20` §6 both gated 7c on whether admission-time fee acknowledgment depends on the full fee system existing or ships as a placeholder checkbox. `16` D14 confirms Track 6d (Fees/Payments/Finance) is now built and test-gated. Record this formally before writing schema:

> Add a new decision to `16-schoolos-decisions-register.md` (next available number) stating: admission-time fee acknowledgment ties to the real `fee_categories` table (school-scoped, already built per D14), not a placeholder checkbox — an applicant acknowledges the specific fee categories the school has defined (tuition/medical/uniform/exam/other), by category id, at submission time. Rationale: 6d closed before 7c started, so the "ship as checkbox pending real fee integration" fallback `19` §10 offered is no longer the cheaper path — building against the real table from the start avoids a second migration later to convert checkbox-acknowledgment rows into category references.

**Build prompt:**
> Build the admission/enrollment application workflow per `19-discovery-hierarchy-gap-closure-plan.md` §10 and discovery doc §12's 11 steps, using the fee-dependency decision above. Add an `admission_applications` table: `school_id` (FK), `status` (enum: submitted, under_review, accepted, rejected, withdrawn — default submitted), `applicant_data` (JSON — name, DOB, parent/guardian info, medical info; a prospective student isn't a `User` yet, so this is pre-User structured data, not a foreign key), `fee_category_acknowledgments` (JSON array of `fee_categories.id`, validated against the submitting school's own categories), `documents` (JSON array of file references), `submitted_at`, `decided_by` (FK → users, nullable), `decided_at` (nullable), `decision_reason` (nullable text, used by reject/withdraw), `resulting_user_id` (FK → users, nullable — set on acceptance) and `resulting_student_enrollment_id` (FK → student_enrollments, nullable — set on acceptance), following `13`'s table-by-table documentation convention.
>
> Build `AdmissionApplicationRepository` with:
> - `submit()` — the one unauthenticated write path (a prospective applicant has no session), validates every acknowledged fee-category id actually belongs to the target school, sets `status = submitted`.
> - `markUnderReview()`, `reject()`, `withdraw()` — `school_admin`-only, standard status transitions, `reject`/`withdraw` require a `decision_reason` (mirror `PromotionRepository::override()`'s `MissingPromotionReasonFailure`-style guard — add an equivalent `Admission`-namespaced exception rather than reusing the Promotion one).
> - `accept()` — the conversion path `19` §10 flagged as new territory (nothing in `06`–`18` designed a not-yet-a-user-becomes-a-user flow), and the part most worth its own short domain-map pass before writing it, even though the surrounding CRUD doesn't need one. Inside one transaction: create a new `User` (`role_id` = `Role::where('key','student')->value('id')`, `school_id` from the application, `name`/`email`/`mobile_no` from `applicant_data`, a random generated password — `User.password`'s `hashed` cast handles the hashing, nothing downstream should ever need to know the raw value, so don't log it or return it in any response body), then call the existing `EnrollmentRepository::enroll()` with that new user's id (this is what actually creates the `student_enrollments` row and triggers `IdentifierService::generateStudentId()` — do not duplicate either of those, `enroll()` already owns them). Record the resulting `user_id`/`student_enrollment_id` back onto the `admission_applications` row and set `status = accepted`, `decided_by`, `decided_at`.
>
> Controllers: a `Public\AdmissionApplicationController` (guest-middleware, matching the `Route::middleware('guest')->group(...)` shape already used for login) with `create()`/`store()` for the public application form, scoped to a school via `schools.short_code` in the URL rather than `school_id` directly (never trust a client-supplied numeric `school_id` for an unauthenticated write, per Ground Rule 0 — resolve the school row from `short_code` server-side, same posture as every other tenant-scoping rule in this codebase, just without an authenticated actor to scope from). An `Admin\AdmissionApplicationController` (`index`/`show`/`markUnderReview`/`reject`/`withdraw`/`accept`), `school_admin`-only, every by-id action carrying the `scope.checked` marker (or documented as body-parameter-resolved and exempt, the same way `Admin\PromotionController::runAutomatic()`/`override()` are documented) per row 8's route-table lint.
>
> Test gate, one test per item: (a) an unauthenticated submission with a valid school `short_code` and valid fee-category ids succeeds; (b) a submission with a fee-category id from a *different* school is rejected (this is the one cross-tenant path 7c introduces without an authenticated actor to scope from — test it explicitly, don't assume the id-ownership check works because it's a one-line `where`); (c) `accept()` creates exactly one `User` with `role = student` and exactly one `student_enrollments` row, both attributed back onto the `admission_applications` row; (d) `accept()` on an already-decided application (accepted/rejected/withdrawn) is rejected, not silently re-run; (e) `reject()`/`withdraw()` without a `decision_reason` are rejected, mirroring `Phase7bTestGateTest`'s empty-reason test for `PromotionRepository::override()`.
>
> Once the test gate passes on a real `php artisan test` run, add the closing entry to `16-schoolos-decisions-register.md` in the D9/D10/D14 "built and verified" format — cite the actual test output (pass count, assertion count), not a description of what the code should do.
