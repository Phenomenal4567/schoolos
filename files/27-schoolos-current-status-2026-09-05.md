# 27 - SchoolOS Current Status (2026-09-05)

**Purpose:** Snapshot of where the codebase actually stands today, verified against a live `php artisan test` run rather than carried forward from the last status note (`26-discovery-hierarchy-status.md`, last verified 2026-09-02). Read that file for the full feature-by-feature discovery checklist; this file records what has changed since and the current test health.

## Stack

- Laravel 13.17 / PHP ^8.3, SQLite for local dev (`DB_CONNECTION=sqlite`).
- Frontend build via Vite 8 + Tailwind 4 (`laravel-vite-plugin`), no SPA framework — server-rendered Blade.
- Test stack: PHPUnit 12.5, `tests/` has 467 tests across Feature (controllers, repositories, services, per-phase gate tests) and a couple of Unit tests.
- No git repository initialized in this working copy (`Is a git repository: false`) — there is no commit history to diff against; `schoolos.zip` in the project root appears to be a manual snapshot backup.

## Test suite health (verified 2026-09-05)

```
php artisan test
467 tests, 1441 assertions, 0 failed, 467 passed
```

The 3 failures recorded earlier today were all in the **Timetable Periods / working-days** feature (migrations dated 2026-09-04: `create_timetable_periods_table`, `add_working_days_to_schools_table`, `add_room_to_timetable_slots_table`) and have since been fixed:

1. **Dead route reference (real bug, fixed).** `resources/views/teacher/timetable/index.blade.php` was already clean in source, but a **stale compiled Blade view cache** in `storage/framework/views/` still held an old compiled copy referencing the already-removed `teacher.timetable.store` route, turning every `GET /teacher/timetable` request into a 500. Fixed by running `php artisan view:clear`. (If this recurs in another environment, it means a deploy isn't clearing compiled views after a route rename/removal — worth adding `artisan view:clear` to the deploy step if it isn't already there.)
2. **Working-days JSON not cast to array (real bug, fixed).** `App\Models\School` had no `array` cast for the `working_days` JSON column. Laravel's query builder auto-JSON-encodes array values on write, so saving worked, but reading `$school->working_days` back returned a raw JSON string. `admin/timetable/settings.blade.php` does `in_array($value, $selectedDays, true)` against that value — which throws a `TypeError` (`in_array()` argument must be an array) the moment a school has saved its working days once, 500-ing the settings page on every subsequent visit. Fixed by adding `'working_days' => 'array'` to `School::casts()`.
3. **Time format mismatch (minor, fixed).** `TimetableRepository::replaceSettings()` stored period `start_time`/`end_time` as `H:i` (e.g. `08:00`); normalized to `H:i:s` to match the `time` column type. The settings view already round-trips either format fine (`Carbon::parse(...)->format('H:i')`), so this was purely a storage-consistency fix, not a display bug.
4. **Wrong test expectation (test-only, fixed).** `TeacherPortal\TimetableControllerTest::test_teacher_cannot_post_to_the_removed_store_route` asserted `404` for `POST /teacher/timetable`, but Laravel correctly returns `405` (Method Not Allowed) since the URI still matches the surviving `GET /timetable` route. Updated the assertion to `assertMethodNotAllowed()`.

Of the four, #1 and #2 were live user-facing 500s; #3 and #4 had no runtime impact. All four are now fixed and covered by the existing test suite (no new tests were needed — the pre-existing tests caught all of them once the underlying code was corrected).

## Feature completeness

See `26-discovery-hierarchy-status.md` for the full breakdown. Summary: the vast majority of the discovery hierarchy (school setup, student management, parent/student/teacher/super-admin portals, attendance, academics, exams/results, promotion, fees/payments, admissions, finance dashboard, communication, exports, auth, RBAC) is implemented and covered by tests. Confirmed still partial/incomplete as of this snapshot:

- No department model/workflow.
- No subject-level attendance (topic-taught capture) — attendance is class/session-level only.
- No per-school toggle for instant attendance notifications.
- Staff attendance only supports manual + QR check-in (no geolocation/selfie/fingerprint).
- No CBT (computer-based testing) delivery mode.
- No WhatsApp channel integration.
- No formal export retention/storage policy.

## Since the last status note (2026-09-02 → 2026-09-05)

New migrations/work not yet reflected in `26-discovery-hierarchy-status.md`:

- `2026_09_04_000001_create_timetable_periods_table.php` — school-defined period grid (period number, start/end time) backing the settings screen above.
- `2026_09_04_000002_add_working_days_to_schools_table.php` — per-school working-days configuration.
- `2026_09_04_000003_add_room_to_timetable_slots_table.php` — room field on timetable slots.
- `2026_09_03_020000_create_staff_profiles_table.php` / `2026_09_03_020001_create_staff_documents_table.php` and `2026_09_03_010000_create_exam_remarks_table.php` / `2026_09_03_000001_create_admission_applications_table.php` predate this note's window slightly but are already reflected in `26-discovery-hierarchy-status.md`'s "Done" list (staff profiles, exam remarks, admissions).

## Suggested immediate next steps

All four issues above are fixed and the suite is green (467/467). `26-discovery-hierarchy-status.md`'s "Suggested Next Build Order" is now the actionable list: subject-level attendance first, then departments, then the lower-priority items (advanced staff-attendance verification, CBT delivery, WhatsApp, export retention) pending product decisions.
