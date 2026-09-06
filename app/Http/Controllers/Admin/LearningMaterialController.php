<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\LearningMaterialRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6 (discovery
 * §7.3), 20-phase-6-8-execution-prompt.md §3.
 *
 * school_admin's authoring surface for learning_materials — store-only,
 * same JSON-only shape as Admin\SchemeOfWorkController/
 * Admin\AnnouncementController. class_section_id/subject_id are both
 * nullable (a school-wide material carries neither), matching
 * LearningMaterialRepository::create()'s own nullable parameters — no
 * update()/destroy() and no index()/show() here, same "read + admin-
 * create" scope restraint. $actor->school_id is the only source of
 * school_id (Ground Rule 0).
 */
class LearningMaterialController extends Controller
{
    public function store(Request $request, LearningMaterialRepository $repository): JsonResponse
    {
        $actor = $request->user();

        try {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'file_path' => ['required', 'string'],
                'material_type' => ['required', 'string', 'in:textbook,notes,other'],
                'class_section_id' => ['nullable', 'integer'],
                'subject_id' => ['nullable', 'integer'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        try {
            $learningMaterial = $repository->create(
                $actor->school_id,
                $actor,
                $data['title'],
                $data['file_path'],
                $data['material_type'],
                $data['class_section_id'] ?? null,
                $data['subject_id'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => ['learning_material' => $e->getMessage()]], 422);
        }

        return response()->json(['data' => $learningMaterial], 201);
    }
}
