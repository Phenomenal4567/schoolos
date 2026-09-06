# 23 — SchoolOS Exam Domain Map (Track 7a)

**Status:** Written retroactively. `16-schoolos-decisions-register.md` D15 and `app/Repositories/PromotionRepository.php`/`tests/Feature/Phase7bTestGateTest.php` already cite this document (`23-schoolos-exam-domain-map.md §2/§4/§5`) as an existing source, but no such file existed anywhere in `files.zip` prior to this pass — 7b was built and closed against a domain-map pass that was never actually written down. This document reconstructs that pass now, from the same direct evidence those citations imply, so the citations become true rather than remaining a broken reference. This is exactly the "closed on a real check, not a doc comment or a chat claim" rule `19` §0 / `01` §5 / `20`'s own binding rule state — a citation to a non-existent document is a smaller version of the same failure.

Per `20-phase-6-8-execution-prompt.md` §6's 7a prompt, this maps `19-discovery-hierarchy-gap-closure-plan.md` §7 and discovery doc §8.1/§8.3 against the actual `Exam`/`ExamMark` models and controllers in `schoolos.zip`, evidence-based, in the `06`–`10` style.

---

## 1. Question this pass answers

Discovery §8.1: *"The platform should not assume every school uses CBT"* — secondary schools may use CBT, primary/crèche schools use **written** (paper) examinations. This raises the practical question that gated 7a before 7b could start: **can a school that runs an exam on paper still get that exam's results into the system, and is that path already built, or does it need new work?**

## 2. Confirmed: current implementation is marks-entry-only, and that already covers the paper-exam case

Direct check against `database/migrations/2026_08_27_000004_create_exams_marks_tables.php`, `app/Models/Exam.php`, `app/Models/ExamMark.php`, and `app/Http/Controllers/TeacherPortal/ExamController.php`:

- `exams` records a name, date, class_section, and subject — nothing about *how* the exam was delivered (no `mode`/`delivery_type` column, no CBT-specific fields anywhere in the schema).
- `exam_marks` records exactly `marks_obtained`, `max_marks`, `recorded_by`, `recorded_at` — a teacher-entered score, full stop.
- `TeacherPortal\ExamController::recordMark()` is the only write path to `exam_marks`. It takes `marks_obtained`/`max_marks` directly from the request body and upserts (`ExamMarkRepository::record()`), the same shape a teacher would use whether the underlying exam was sat on paper or on a screen elsewhere.

**Conclusion: yes, results can be uploaded manually, and yes, a paper exam's results can go into the system today, with no new build required.** The existing flow already *is* "teacher marks a paper exam, then types the scores into SchoolOS" — there is no dependency on any CBT engine, no requirement that a score originate from an auto-graded flow. Phase 4's scope was, and remains, marks-entry: a teacher (or admin) is the one keying in results, regardless of how the exam was administered. This matches `19` §7's own hedge — "the discovery doc's CBT-vs-written distinction is a delivery-mode question the existing schema may or may not already accommodate" — and resolves it: the schema accommodates written/paper delivery by simply not caring how the exam was delivered, only what the recorded score was.

## 3. Confirmed not built: CBT-specific delivery

No timed, auto-graded question-and-answer flow exists anywhere in `app/` — no `Question`, `QuestionOption`, `CbtAttempt`, or equivalent model; `exams`/`exam_marks` have no columns that would carry a CBT session's state (start time, per-question responses, auto-computed score). If a school wants students to sit an exam *inside* SchoolOS with auto-grading, that is new, unbuilt surface — separate from, and unblocking of, the paper/manual-entry path in §2, which is what 7b (Promotion) actually depends on.

## 4. Confirmed not built: result delivery specifics

- **PDF/printable result generation** (discovery §8.3): not built. No PDF-rendering code anywhere in the exam controllers; parent/student portals can view marks in-page but there is no download-as-PDF action.
- **Proprietor/proprietress remark field** (discovery §8.2): not built. No such column on `exams` or `exam_marks`.
- **"Number of times school opened / number of times student attended" aggregate fields** (discovery §8.2): not built as exam-attached fields. Attendance itself is tracked (Phase 3, `attendance_records`), but nothing joins an attendance count onto a result view or export.
- **Aggregate score** (needed by 7b's promotion criteria): no `aggregate` column exists anywhere. `PromotionRepository::computeAggregate()` (7b) defines this itself as the mean exam percentage across a student's `ExamMark` rows in the relevant class section — an engineering default this pass flags as still open for school-facing confirmation, not a decision this domain-map pass or `19`/`20` ever made.

## 5. Decision for product (unchanged from `19` §7, now with an answer to the narrower manual-upload question)

Whether to build CBT-mode delivery, PDF result generation, and the proprietor-remark/attendance-aggregate fields remains an open product-scope call — this pass doesn't resolve it, only narrows it. What this pass *does* resolve: **the marks-entry/manual-upload path is not blocked on that decision.** A school running exams entirely on paper is fully served by the existing `recordMark()` flow today, and 7b (Promotion) is safe to build against `exam_marks` as it stands.

## 6. Unblocks

Per `20` §8: "Once 7a (exam domain-map pass) completes: 7b build (Promotion)." This document closes that gate. 7b was, in practice, already built against these findings (see D15 and `Phase7bTestGateTest`) — this document supplies the missing paper trail rather than changing 7b's shape.
