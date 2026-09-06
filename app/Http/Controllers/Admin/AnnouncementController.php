<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\AnnouncementRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5
 *
 * school_admin's authoring surface: may post either audience_type,
 * matching AnnouncementRepository::create()'s role rules. See that
 * class's doc comment for the full validation this delegates to.
 */
class AnnouncementController extends Controller
{
    public function store(Request $request, AnnouncementRepository $repository): JsonResponse
    {
        $actor = $request->user();

        // This controller is a JSON-only surface (success returns 201 JSON,
        // see below), so a validation failure must also come back as JSON
        // 422 — not the default redirect-back-with-session-errors behavior
        // ValidationException falls back to when the request doesn't
        // itself ask for JSON (e.g. no Accept: application/json header).
        try {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string'],
                'audience_type' => ['required', 'string', 'in:school,class_section'],
                'class_section_id' => ['nullable', 'integer'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        try {
            $announcement = $repository->create(
                $actor->school_id,
                $actor,
                $data['title'],
                $data['body'],
                $data['audience_type'],
                $data['class_section_id'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errors' => ['audience' => $e->getMessage()]], 422);
        }

        return response()->json(['data' => $announcement], 201);
    }
}
