# 08 — Parent Domain Map (GegoK12, verified from real `app/` source)

**Status:** Verified against actual controllers and models. Confirms and extends `04-route-map.md` §2's finding (no parent web dashboard) — parents are, as suspected, mobile/API-only, and this pass identifies exactly which controllers serve them.

---

## 1. Parents use the top-level `Api/*` controllers directly — no `Api/Parent/` namespace

Unlike Teacher (which has both `Http/Controllers/Teacher/*` for web and a parallel `Http/Controllers/Api/Teacher/*` for mobile), there is no dedicated parent namespace at all. The plain `App\Http\Controllers\Api\*` controllers — `ChildrenController`, `FeesController`, `UserController`, `TokenController`, `LoginController`, `AttendanceController`, `HomeworkController`, `AssignmentController`, `MarksController`, `TimetableController`, `LeaveController`, `DisciplineController`, `NoticeBoardController`, `EventsController`, and others (27 controllers total, `wc -l` totals ~4,100 lines) — are the parent-facing (and likely also student-facing, per `06-student-domain-map.md` open item) mobile API surface. Confirmed directly: `ChildrenController` uses `$request->user()->children` (a parent-only relation) and is gated by `auth:sanctum` only — no explicit `usergroup_id == 7` middleware check anywhere on it.

## 2. `Api\ChildrenController` — the parent's core "which students are mine" endpoint

```php
public function listChildren(Request $request) {
    $parentUser = $request->user();
    $children = $parentUser->children->map(fn($item) => [...from student_parent_links...]);
}
public function showChildren(Request $request, $id) {
    $children_id = $request->user()->children->pluck('student_id')->toArray();
    $children = User::where('id', $id)->whereIn('id', $children_id)->first();   // correctly scoped
}
```

`showChildren($id)` is a **positive example** worth calling out: it explicitly intersects the requested `$id` against the caller's own linked children before returning anything, rather than trusting `$id` alone. This is exactly the pattern `Api\AttendanceController::index($student_id)` is missing (`11-security-findings.md` F20) — same domain, same shape of risk, handled correctly here and not there. Worth citing as a positive precedent when designing SchoolOS's scope layer: the fix for F20 isn't hypothetical, a working example already exists two files away in the same codebase.

## 3. Two separate, only-partially-consistent parent-child linkage mechanisms

`05-database-map.md` §5 already documented `student_parent_links` (`parent_id`, `student_id`, both plain FKs to `users`) as the clean, explicit relationship table the handoff wants. This pass found the `User` model actually exposes **two different relationship paths**, not one:

1. **`student_parent_links`-based** — `User::children()` (`hasMany(StudentParentLink, 'parent_id')`) and `User::parents()` (`hasMany(StudentParentLink, 'student_id')`). This is what `Api\ChildrenController` actually uses, and what the schema doc already covers.
2. **`users.ref_id`-based** — a *separate*, self-referencing foreign key directly on the `users` table (`ref_id` is in `User::$fillable`). `User::members()` (`hasMany(User, 'ref_id', 'id')`) walks this second mechanism. It's consumed by `User::mother()` (`$this->members()->whereHas('parentprofile', fn($q) => $q->where('relation', 'mother'))`) — but **not** by `User::father()`, which instead does `$this->whereHas('parentprofile', fn($q) => $q->where('relation', 'father'))` directly on the calling model, skipping `members()` entirely.

**This is very likely a real bug, not just an inconsistency:** `mother()` and `father()` read as though they're meant to be symmetric (find this student's mother / find this student's father), but they use different relationship traversals. `father()`'s direct `whereHas('parentprofile', ...)` call only makes sense if the *student* model itself has `parentprofile` rows — but `parentprofile()` is `hasMany(ParentProfile, 'user_id', 'id')`, i.e., profile rows belonging to a *parent* user, not a student. Called on a student instance, `father()` is almost certainly checking the wrong record and would return empty/incorrect results in the normal case, while `mother()` (which correctly walks through `members()` to the family-group member records first) works as intended. **Needs a live query or test fixture to fully confirm the practical impact** (e.g., whether `father()` is called anywhere that matters, or is itself unused/dead code) — grep shows no controller currently calling `father()` or `mother()` directly, so this may be latent/unused rather than actively broken in a reachable code path. Flagging per the audit's existing "flag now so it isn't lost" convention (same caveat pattern as F5/F6 originally used).
- **`ref_id`/`members()` is not used anywhere outside `User.php` itself** (confirmed via grep across `app/Http/Controllers` and `app/Traits`) — so this second mechanism appears to be vestigial: present in the schema and model, exercised only by the asymmetric/likely-broken `father()`/`mother()` pair, and not the mechanism any actual controller (including the parent-facing API) relies on. `student_parent_links` is the live, load-bearing mechanism.

## 4. SchoolOS implications

- `05-database-map.md` §5's positive assessment of `student_parent_links` stands — it's confirmed as the actually-used mechanism. No change needed to that conclusion.
- **New finding for the naming/duplication category (alongside F8, F9, and the `usergroup_id` policy-map duplication in F18):** `ref_id` on `users` is a second, largely-dead parent-child linking mechanism sitting alongside the real one. SchoolOS should have exactly one parent-child relationship table, with no parallel self-referencing FK on the user table that only some code paths use.
- `Api\ChildrenController::showChildren()` is a genuinely good pattern — intersecting the request parameter against the caller's own relationship data before querying. SchoolOS should treat this as the template for every "fetch a specific child's data" endpoint, applied consistently (which, per F20, GegoK12 itself does not do consistently even within its own parent/student API surface).

---

**Open items:**
- Confirm whether `father()`/`mother()` are truly unreferenced (dead code) or called from a view/Blade template not covered by the `app/` source pack (templates weren't included in `app.7z`).
- `Api\FeesController`, `Api\MarksController`, `Api\TimetableController`, `Api\LeaveController` (parent-facing views of fees/marks/timetable/leave) not yet individually audited for the same ownership-check pattern as F20 — given F20 was found in the very next controller over, these should be treated as suspect until checked, not assumed safe.
- Whether student-authenticated requests hit these same top-level `Api/*` controllers (shared with parents) — still open from `06-student-domain-map.md`.
