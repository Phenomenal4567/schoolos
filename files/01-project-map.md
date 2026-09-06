# 01 — Project Map

**Status:** Written retroactively. The original handoff (`GegoK12_SchoolOS_Engineering_Handoff.md`, §46) specified this as the *first* document to produce, before `02`. In practice the audit started directly with `02-authentication-map.md` and this index is being filled in after `02`–`12` already exist, once F18/F20's significance made clear that a reader arriving mid-audit needed one place to get oriented. Nothing below is a new finding — it's a map of what the other documents already established, plus how they relate to each other and to the original handoff.

---

## 1. What this audit is and isn't

**GegoK12** is a Laravel-based school-management ERP being studied as a reference implementation — not a system to clone. The objective (per the handoff, §2) is threefold: reverse-engineer how a real school ERP models users/schools/classes/attendance, identify architectural mistakes worth learning from, and use both to design **SchoolOS**, a cleaner system that doesn't inherit GegoK12's incidental complexity or incomplete flows.

Two source packs feed this audit:
- **`app/`** — the actual Laravel application source (controllers, models, providers, traits, policies). This is the primary evidence base for every domain map and every security finding — claims are marked "confirmed via real source" when they trace to this pack, distinct from earlier grep-only claims.
- **Roadmap documents** (`02`–`14`, this file included) — the audit's own output, produced incrementally as more of `app/` was read.

`routes/` and `database/migrations/` were available for earlier passes (`04-route-map.md`, `05-database-map.md`) but were **not** included in the `app/`-only pack used for `10-api-map.md` — route-level facts in `10` are therefore carried over from `04` rather than re-verified. Flagged as an open item there; worth re-confirming if a fuller source pack becomes available.

## 2. Roadmap status

| Doc | Covers | Status |
|---|---|---|
| `01-project-map.md` | This file | Written retroactively, this pass |
| `02-authentication-map.md` | Login flows (web + API), password reset, OTP | Complete |
| `03-role-permission-map.md` | The three parallel authorization systems (`usergroup_id`, Laratrust, `permission_user`) | Complete |
| `04-route-map.md` | Route-file-to-middleware wiring via `RouteServiceProvider` | Complete |
| `05-database-map.md` | Tenant/academic/attendance schema core (~18 of 108 migrations reviewed table-by-table) | Core complete; ~90 migrations (payroll, library, messaging, transport, etc.) catalogued by filename only |
| `06-student-domain-map.md` | Student web controllers (19) | Complete |
| `07-teacher-domain-map.md` | Teacher web controllers (40) + approval sub-namespace | Domain shape + two headline findings covered; not every controller reviewed line-by-line |
| `08-parent-domain-map.md` | Parent-facing API controllers (no parent web surface exists — see F10) | Complete |
| `09-attendance-map.md` | Attendance write path end-to-end | Complete |
| `10-api-map.md` | Full `Api/*` + `Api/Teacher/*` controller audit (48 files, ~8,900 lines) | Complete for controllers reviewed; 5 controllers (`EventGalleryController`, `NoticeBoardController`, `TaskController`, `LessonPlanController`, `FeedbackController`, `Search/UserSearchController`) flagged as suspect-not-reviewed |
| `11-security-findings.md` | Running numbered finding log | 22 findings (F1–F22), open-ended |
| `12-schoolos-architecture.md` | SchoolOS design synthesized from findings | Draft; §3a (Scope enforcement) is the most evidence-backed section |
| `13-schoolos-database-schema.md` | SchoolOS schema | Not started |
| `14-schoolos-implementation-plan.md` | Build sequencing | Not started |

## 3. How the documents relate to each other

The dependency shape isn't linear — it's evidence flowing inward toward `12`, then `12` flowing forward into `13`/`14`:

```
02, 03, 04, 05   (mechanism-level: how auth/roles/routes/schema actually work)
        │
        ▼
06, 07, 08, 09   (role-domain passes: student/teacher/parent/attendance,
                  each one re-using and sometimes correcting 02-05's claims —
                  e.g. 07 sharpens 03's role-system finding into F18)
        │
        ▼
10               (cross-cutting API pass — generalizes findings that 06-09
                  found in individual controllers, e.g. F20 → F21/F22)
        │
        ▼
11               (every confirmed defect from any pass above, numbered,
                  never renumbered — the single citable source of truth)
        │
        ▼
12               (design decisions, each one citing a specific F-number —
                  no undocumented "best practice" claims)
        │
        ▼
13, 14           (schema + build plan — implement what 12 specified)
```

`11-security-findings.md` is the hinge: every domain-map document (`06`–`10`) contributes findings to it rather than drawing conclusions in isolation, and `12` is not allowed to cite anything that isn't a numbered finding there (see `12`'s traceability table). This is why the F18/F20 gap mattered enough to fix retroactively — a design document citing an unlogged finding breaks the citation chain the whole audit depends on.

## 4. What's confirmed vs. still open, by area

| Area | Confirmed | Still open |
|---|---|---|
| **Auth** | 3 parallel authorization systems (F8); OTP/reset flow fully traced (F4); registration-number login likely crashes (F5); uncaught `\Error` in reset flow (F16) | Live repro of F5/F6 not done (static reading only) |
| **Tenant isolation** | Schema-level `school_id` FK on every domain table (`05` §1) is sound | Enforcement is scattered conditionals, not structural (F3) |
| **Authorization / scope** | Teacher Gates check tenant only (F18); attendance IDOR (F20); leave-application surface fully unscoped (F21); homework/assignment show-update unscoped while destroy is checked (F22) | Whether students share the parent `Api/*` surface or have their own namespace — asked in `06`, `08`, and again in `10`, still unconfirmed |
| **Attendance** | Boolean status, no unique constraint, class-session-granularity duplicate guard with TOCTOU race, no correction capability at all (F13, `09`) | — |
| **Routing** | Full route-file → middleware map (`04`); no parent web routes at all (F10); shared `/accountant` prefix with different middleware (F12) | Route-level API detail needs re-confirmation once `routes/` is available again (see `10`'s open items) |
| **Data model** | 4-way `standards_link` join for class-in-a-year (`05` §2); medical data mixed into academic enrollment table (F14); misleading `student_history` table name (F15) | ~90 unreviewed migrations (payroll, library, messaging, transport) |

## 5. Reading order for a new arrival

If picking this audit up mid-stream: read this file, skim `11-security-findings.md`'s headers for the finding list, then read `12-schoolos-architecture.md` — its traceability table doubles as a compressed summary of everything that mattered enough to change a design decision. Go to the individual domain maps (`02`–`10`) only for the full evidence behind a specific finding.

---

**Next:** `13-schoolos-database-schema.md` and `14-schoolos-implementation-plan.md` are the only unstarted items. `12`'s §3a (Scope enforcement) is now stable enough (4 findings, 3 severity tiers) to build a schema against.
