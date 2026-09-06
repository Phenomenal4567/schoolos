# 03 — Role / Permission Map (GegoK12, verified from local source)

**Status:** Verified against actual model files and a full-repo grep for `usergroup_id`. This confirms the handoff's Section 6 concern — there are genuinely **three parallel, weakly-connected authorization systems** in GegoK12.

---

## 1. System A — `usergroup_id` (the real authorization backbone)

Defined as class constants on `App\Models\User`, confirmed complete list (13 groups, not just the 3 mentioned in the handoff):

| ID | Constant | Role |
|----|----------|------|
| 1 | `SITEADMIN_USERGROUP_ID` | Site/platform admin (bypasses school scoping) |
| 2 | `SITESUBADMIN_USERGROUP_ID` | Site sub-admin |
| 3 | `SCHOOLADMIN_USERGROUP_ID` | School admin (principal-level) |
| 4 | `SCHOOLSUBADMIN_USERGROUP_ID` | School sub-admin |
| 5 | `TEACHER_USERGROUP_ID` | Teacher |
| 6 | `STUDENT_USERGROUP_ID` | Student |
| 7 | `PARENT_USERGROUP_ID` | Parent |
| 8 | `LIBRARIAN_USERGROUP_ID` | Librarian |
| 9 | `ALUMNI_USERGROUP_ID` | Alumni/old student |
| 10 | `RECEPTIONIST_USERGROUP_ID` | Receptionist |
| 11 | `ACCOUNTANT_USERGROUP_ID` | Accountant |
| 12 | `STOCK_KEEPER_USERGROUP_ID` | Stock keeper |
| 13 | `NON_TEACHING_USERGROUP_ID` | Non-teaching staff |

This is genuinely the load-bearing authorization concept in the app. It's used for:
- **Query scoping** — hundreds of controllers filter by `usergroup_id` directly (`role-search.txt` shows 100+ hits across `Admin/`, `Api/`, `Librarian/`, `Receptionist/`, `Payroll/` controllers).
- **Type checking** — `User::isTeacher()`, `isStudent()`, `isParent()`, `isAdmin()`, `isStaff()`, etc. (all present, all just `usergroup_id === constant`).
- **Polymorphism** — `User::getSpecializedInstance()` returns a subclass (`TeacherUser`, `StudentUser`, `ParentUser`, `AlumniUser`, `AdminUser`, `LibrarianUser`, `AccountantUser`) based on `usergroup_id`, and those subclasses (seen: `AdminUser.php`, `ParentUser.php`, `AccountantUser.php`) re-implement their own `usergroup_id`-based query scopes independently of the base `User` scopes.
- **Route/credential gating** — API login endpoints hardcode `usergroup_id` into the `Auth::attempt()` call itself (see 02-authentication-map.md §2).

**Problem:** this is a bare integer FK with no formal enum/state machine — every one of those checks is a hand-written `where('usergroup_id', N)` or `in_array(...)`. There's no single source of truth query; if a 14th role were added, someone would need to find and update every one of these call sites by hand.

## 2. System B — Laratrust roles/permissions (parallel, thinner layer)

- `User` also uses `Laratrust\Traits\HasRolesAndPermissions`.
- `Role` extends `Laratrust\Models\Role`; `Permission` extends `Laratrust\Models\Permission`; junction tables `role_user`, `permission_user`, `permission_role` all exist (`2020_02_18_044623_laratrust_setup_tables.php`).
- **This system is used far more narrowly** — grep shows `hasRole('principal')` and `hasRole('leave_checker')` / `hasRole('leave_applier')` / `hasRole('student_leave_checker')` / `hasRole('transport_driver')` as essentially the *only* named-role checks in the whole codebase, concentrated in `Teacher/Approval/*`, `Teacher/LeaveController`, `Teacher/LessonPlan*`, and API mirrors of the same.
- Seeders (`RoleUsersTableSeeder.php`, `UsersTeacherTableSeeder.php`, `StandardsLinkTableSeeder.php`) assign raw numeric `role_id` values (1, 2, 3, 4) with no named constants — same "magic number" anti-pattern as `usergroup_id`, but for a system with much smaller footprint.

**Conclusion:** Laratrust roles exist almost entirely to express **one cross-cutting distinction inside the Teacher usergroup** — "is this teacher also a principal / leave-approver / driver" — i.e., a *secondary, in-role privilege*, not a replacement for `usergroup_id`. It is not proof of a mature RBAC layer; it's a bolt-on for a handful of teacher-specific flags.

## 3. System C — `permission_user` (per-user permission overrides)

- `PermissionUser` model + `permission_user` table: direct user↔permission grants, bypassing roles entirely.
- Confirmed wired up (`User::permissionUser()` relation exists), but **zero non-framework call sites appeared in the grep** — no controller was seen actually granting or checking one of these.
- **`config/laratrust.php` reviewed — does not resolve this.** `'checkers' => ['user' => 'default', 'role' => 'default']` confirms Laratrust's own built-in permission-resolution method is active (not the raw-query variant), and `'permissions_as_gates' => false` confirms permissions are **not** exposed via Laravel's `Gate`/`can()` system. That rules out one place they might have been checked (`->can('x')` calls), but doesn't prove `permission_user` grants are unused elsewhere — Laratrust's internal `hasPermission()` checks would still consult that table without it showing up as a literal string `permission_user` in a grep. **Still open** — would need a grep for `hasPermission(` and `->permissions` specifically, or the `Role` model's permission-check internals, to close this out.
- Also confirmed via `laratrust.php`: `'teams' => ['enabled' => false]`, so the `Team` model referenced in the config is inert — not a real concept in this deployment, safe to ignore for SchoolOS purposes.
- The Laratrust middleware config (`'handling' => 'abort'`, 403 on failure) matches what's now confirmed in `Kernel.php`: the `role`/`permission`/`ability` middleware aliases exist but, per the route-file grep so far, appear to see far less use than the 13 hand-written `MustBe*` middleware classes (see `02-authentication-map.md` §6) — another sign System B (Laratrust) is present but structurally underused compared to System A.

## 4. A fourth, informal grouping mechanism: `Group` / `GroupMember`

- `Group` (`groups` table) + `GroupMember` (`group_members`, polymorphic `member_type`) — a generic tagging/grouping mechanism tied to a `StandardLink` (class/section), with `member_id`/`member_type` suggesting it can hold any model type, not just users.
- This looks like ad-hoc infrastructure for things like "chat groups" or "class groups" rather than authorization — but it's worth flagging as a **fourth loosely-related concept** sitting near the role system that a new engineer could confuse with actual RBAC.

## 5. Net assessment (confirms + sharpens the handoff's Section 6 concern)

GegoK12 has:
1. `usergroup_id` — coarse, load-bearing, hand-checked everywhere, no enum.
2. Laratrust roles — fine-grained, but only meaningfully used for ~4 teacher-side flags.
3. `permission_user` — wired up, apparently unused in application code (unconfirmed).
4. `Group`/`GroupMember` — unrelated grouping mechanism, adjacent enough to cause confusion.

None of these compose into a single "what can this user do" answer — a developer has to know that `usergroup_id` answers "what broad role" while Laratrust `hasRole()` answers "what teacher sub-privilege," and that the two are checked with entirely different syntax in different files.

## 6. Recommendation for SchoolOS (carried over from handoff, now evidence-backed)

Collapse to two real concepts:
- **Role** — replaces `usergroup_id` as an enum/lookup table, not a raw int; answers "what can this user do."
- **Scope** — replaces the ad-hoc `school_id` + parent/child + teacher-assignment checks scattered across controllers; answers "which records can they act on."

Do **not** carry forward a third "sub-privilege" layer bolted onto one role only (the Laratrust `principal`/`leave_checker` pattern) — model "teacher who can approve leave" as a permission on the Teacher role, not a separate role system.

## 7. Confirmed from `Kernel.php`: a *fifth* parallel authorization surface

Route middleware aliases in `app/Http/Kernel.php` add a layer not previously mapped: 13 dedicated `MustBe*` middleware classes (`MustBeSchoolAdmin`, `MustBeTeacher`, `MustBeParent`, etc.) sitting alongside Laratrust's generic `role`/`permission`/`ability` middleware aliases. These almost certainly just re-check `usergroup_id` (consistent with the pattern seen everywhere else — e.g. `MustBeSchoolSubAdmin.php` was already confirmed via grep to do raw `usergroup_id == N` checks), but they're a structurally separate mechanism from both System A's model-level checks and System B's Laratrust role checks — a route can be gated by a `MustBe*` middleware, a `usergroup_id` check inside the controller, a Laratrust `hasRole()` call, or some combination, with no single place to look. Full detail in `02-authentication-map.md` §6.

## 8. Confirmed: specialized `User` subclasses do NOT self-enforce their own type

Reviewed `ParentUser`, `StudentUser`, `TeacherUser` (all in `App\Models\Users\`). This is the most important finding from this pass:

**None of these classes carry a global scope that restricts queries to their own `usergroup_id`.** `TeacherUser extends User` with no `booted()`/global scope override, and the same for `StudentUser` and `ParentUser`. Instead, restriction to the "right" `usergroup_id` is applied **inconsistently, scope-by-scope, by hand**:

- `ParentUser`: `scopeByFirstName`, `scopeByLastName`, `scopeByEmail`, `scopeByQualification`, `scopeByOccupation`, `scopeByStudentStandard` all correctly add `->where('usergroup_id', self::PARENT_USERGROUP_ID)` — but `scopeByFullName`, `scopeByMobileNo`, and `scopeByStudentName` **do not**.
- `StudentUser`: **none** of its three scopes (`scopeByStandard`, `scopeByTransport`, `scopeByAdmissionNumber`) filter by `usergroup_id` at all.
- `TeacherUser`: same — none of `scopeByQualification`, `scopeByDesignation`, `scopeByJobType`, `scopeByEmployeeId` filter by `usergroup_id`.

**Concrete consequence:** `StudentUser::byAdmissionNumber('12345')->get()` does not actually restrict results to students — it queries the entire `users` table by `registration_number LIKE '12345%'` with no usergroup constraint, so it could just as easily match a teacher or parent whose registration number happens to collide. The class name (`StudentUser`) promises a scoped, type-safe query; the implementation only sometimes delivers that promise, and a caller has no way to tell which scopes are safe without reading each one.

**Also confirmed:** `getChildren()` is duplicated near-verbatim between `ParentUser` and `StudentUser` — same body, same `userStudent`/`children` traversal — rather than shared via the base class or a trait.

**SchoolOS implication (sharpens the Section 6 recommendation further):** if SchoolOS keeps a specialized-subclass pattern per role at all, each subclass must enforce its own type constraint via a global Eloquent scope applied once in the class, not rely on every individual query-scope method remembering to add the same `where()` clause. This is a textbook case of "convention that isn't enforced" — exactly the kind of bug class the handoff's Section 42 test list (tenant isolation, resource ownership) is meant to catch structurally.
