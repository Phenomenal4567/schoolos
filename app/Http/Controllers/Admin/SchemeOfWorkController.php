<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\SchemeOfWorkRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6 (discovery
 * §7.1), 20-phase-6-8-execution-prompt.md §3.
 *
 * school_admin's authoring surface for scheme_of_work — store-only,
 * matching Admin\AnnouncementController's shape (JSON-only: 201 with
 * the created row, or a 422 with validation/repository errors as JSON,
 * never a redirect-back flow). No update()/destroy() and no index()/
 * show() here — matches this pass's "read + admin-create" scope, the
 * same restraint TeacherPortal\LessonPlanController's doc comment
 * documents for Phase 4's four generic resources. $actor->school_id is
 * the only source of school_id (Ground Rule 0) — never taken from
 * client input.
 */
class SchemeOfWorkController extends Controller
{
    public function store(Request $request, SchemeOfWorkRepository $repository): JsonResponse
    {
        $actor = $request->user();

        try {
            $data = $request->validate([
                'academic_term_id' => ['required', 'integer'],
                'class_section_id' => ['required', 'integer'],
                'subject_id' => ['required', 'integer'],
                'content' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        try {
            $schemeOfWork = $repository->create(
                $actor->school_id,
                $actor,
                $data['academic_term_id'],
                $data['class_section_id'],
                $data['subject_id'],
                $data['content'],
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => ['scheme_of_work' => $e->getMessage()]], 422);
        }

        return response()->json(['data' => $schemeOfWork], 201);
    }
}
