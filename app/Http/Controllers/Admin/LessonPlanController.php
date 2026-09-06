<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\LessonPlan\UnauthorizedLessonPlanReviewFailure;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Subject;
use App\Repositories\LessonPlanRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §4, §5, §6 (F30, F31)
 *
 * First controller/route calling LessonPlanRepository::approve()/
 * reject() — both methods predate any route calling them, the same
 * "built and tested, not yet wired to a controller" state
 * ClassSectionController::assignTeacher() closed for
 * ClassSectionRepository (see that controller's own doc comment).
 *
 * Both actions resolve an existing lesson_plans row by ID, so both
 * routes carry 'scope.checked' (Phase1TestGateTest's row 8 lint). The
 * repository's own review() method is what actually performs the
 * tenant + role check (F30/F31's fix) — this controller does not
 * duplicate that check, only converts UnauthorizedLessonPlanReviewFailure
 * into a redirect-with-error rather than a 404, since this is a web form
 * action (like every other Admin\* controller) not a JSON API — a
 * school_admin who reaches this route at all is already inside the
 * 'role:school_admin' middleware group, so failure here means the
 * specific lesson plan didn't resolve in their tenant, not that their
 * role is wrong.
 */
class LessonPlanController extends Controller
{
    public function create(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $classSections = $scope
            ->tenantScope(ClassSection::query(), $actor)
            ->with(['standard', 'section'])
            ->get();

        $subjects = $scope->tenantScope(Subject::query(), $actor)->orderBy('name')->get();

        return view('admin.lesson-plans.create', [
            'classSections' => $classSections,
            'subjects' => $subjects,
        ]);
    }

    public function store(Request $request, LessonPlanRepository $repository): Response
    {
        $actor = $request->user();

        $data = $request->validate([
            'class_section_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'document' => ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx,txt', 'max:10240'],
        ]);

        $documentPath = $request->file('document')->store('lesson-plans', 'local');

        try {
            $lessonPlan = $repository->createByAdmin(
                $actor->school_id,
                $data['class_section_id'],
                $data['subject_id'],
                $data['title'],
                $data['content'] ?? '',
                $documentPath,
                $actor
            );
        } catch (\InvalidArgumentException $e) {
            Storage::disk('local')->delete($documentPath);

            if ($request->wantsJson()) {
                return response()->json(['errors' => ['lesson_plan' => $e->getMessage()]], 422);
            }

            return back()->withErrors(['lesson_plan' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $lessonPlan], 201);
        }

        return redirect()->route('admin.lesson-plans.create')->with('status', 'Lesson document uploaded.');
    }

    public function approve(Request $request, LessonPlanRepository $repository, int $lessonPlan): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        try {
            $repository->approve($lessonPlan, $request->user(), $data['note'] ?? null);
        } catch (UnauthorizedLessonPlanReviewFailure) {
            abort(404);
        }

        return back()->with('status', 'Lesson plan approved.');
    }

    public function reject(Request $request, LessonPlanRepository $repository, int $lessonPlan): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        try {
            $repository->reject($lessonPlan, $request->user(), $data['reason']);
        } catch (UnauthorizedLessonPlanReviewFailure) {
            abort(404);
        }

        return back()->with('status', 'Lesson plan rejected.');
    }
}
