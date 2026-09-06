<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ScopeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6
 *
 * Shared by every TeacherPortal/ParentPortal/StudentPortal controller for
 * the four generic Phase 4 resources (TimetableSlot, LessonPlan,
 * Assignment, Exam) — all four are scoped identically per 17 §3's table
 * ("generic class_section_id dispatch — no new code"), so the
 * tenantScope()->relationshipScope() resolution these two methods wrap is
 * the same block AttendanceController::index()/show() already
 * establishes, just parameterized over $modelClass instead of being
 * written out twelve times (four resources × three portals). Each
 * controller's own index()/show() stays a thin wrapper naming its own
 * model class and eager-loads — this trait decides *which rows* are
 * visible to resolve from, never what shape gets returned to the client.
 *
 * Deliberately does not attempt to cover store() — that path differs per
 * resource (validation rules, which repository, which create()
 * signature has no shared shape worth abstracting), and only exists on
 * TeacherPortal's controllers to begin with.
 */
trait ResolvesScopedAcademicResource
{
    /**
     * The actor's own visible rows for $modelClass — id-less, no
     * 'scope.checked' needed (same as AttendanceController::index()).
     *
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $with
     */
    protected function scopedIndex(Request $request, ScopeService $scope, string $modelClass, array $with = []): Collection
    {
        $actor = $request->user();

        return $scope
            ->relationshipScope(
                $scope->tenantScope($modelClass::query(), $actor),
                $actor,
                $modelClass
            )
            ->with($with)
            ->get();
    }

    /**
     * A single $modelClass row by id, resolved through the identical
     * tenantScope()->relationshipScope() intersection scopedIndex() uses
     * above, narrowed to one row via find() rather than a bare
     * ::find($id) on the unscoped model. Returns null on a miss rather
     * than aborting itself — 404-not-403 discipline stays the calling
     * controller's own abort(404) call site, matching every other by-ID
     * resolution in this bundle (see AttendanceController's own doc
     * comment) rather than this trait making that call on the
     * controller's behalf.
     *
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $with
     */
    protected function scopedFind(Request $request, ScopeService $scope, string $modelClass, int $id, array $with = []): ?Model
    {
        $actor = $request->user();

        return $scope
            ->relationshipScope(
                $scope->tenantScope($modelClass::query(), $actor),
                $actor,
                $modelClass
            )
            ->with($with)
            ->find($id);
    }
}
