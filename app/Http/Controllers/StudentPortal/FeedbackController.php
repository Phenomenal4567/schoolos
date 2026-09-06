<?php

namespace App\Http\Controllers\StudentPortal;

use App\Exceptions\Communication\UnauthorizedFeedbackFailure;
use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\User;
use App\Repositories\FeedbackRepository;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §3, §4, §6, §8
 *
 * index()/show() reuse ResolvesScopedAcademicResource — Feedback carries
 * student_id, the same generic scoping column
 * ScopeService::studentRelationshipScope() already dispatches on (18 §4:
 * "no new ScopeService code, same as AssignmentSubmission's read side"),
 * so a student sees their own feedback with no
 * additional query logic beyond what the trait's tenantScope()->
 * relationshipScope() intersection already provides. store() delegates
 * the write-time student_id ownership check (F27) entirely to
 * FeedbackRepository::create(); an UnauthorizedFeedbackFailure becomes
 * 404, never 403, matching every other scope violation in this bundle.
 */
class FeedbackController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $feedback = $this->scopedIndex($request, $scope, Feedback::class, ['student', 'recipientTeacher']);

        // View-only data for the "Send feedback" form below — the
        // school's teachers, for the optional "specific teacher"
        // recipient. No children list here (unlike ParentPortal's own
        // index()) — a student's feedback is always about themself, per
        // this task's own scoping note, so the form has no student_id
        // input to populate options for at all.
        $teachers = $scope->tenantScope(User::query(), $actor)
            ->whereHas('role', fn ($q) => $q->where('key', 'teacher'))
            ->orderBy('name')
            ->get();

        return view('student.feedback.index', [
            'feedback' => $feedback,
            'teachers' => $teachers,
        ]);
    }

    public function show(Request $request, ScopeService $scope, int $feedback): View
    {
        $resolved = $this->scopedFind($request, $scope, Feedback::class, $feedback, ['student', 'recipientTeacher']);

        if ($resolved === null) {
            abort(404);
        }

        return view('student.feedback.show', ['feedback' => $resolved]);
    }

    /**
     * The "Send feedback" HTML form's own submit target (a plain
     * `<form method="POST">`, no fetch()) — see
     * TeacherPortal\TimetableController::store()'s doc comment for why
     * both the caught InvalidArgumentException and the success path now
     * branch on wantsJson(). student_id is submitted as a hidden input
     * set to the acting student's own id, not a visible/chosen field —
     * FeedbackRepository::create()'s own assertOwnsStudent() is what
     * actually enforces self-only, not this form (see this task's own
     * scoping note); the hidden field just satisfies store()'s existing
     * 'required' validation rule for a value the student never picks.
     */
    public function store(Request $request, FeedbackRepository $repository): Response
    {
        $actor = $request->user();

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'message' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'recipient_type' => ['required', 'string', 'in:school,teacher'],
            'recipient_teacher_id' => ['nullable', 'integer'],
        ]);

        try {
            $feedback = $repository->create(
                $actor->school_id,
                $actor,
                $data['student_id'],
                $data['message'],
                $data['category'] ?? null,
                $data['recipient_type'],
                $data['recipient_teacher_id'] ?? null,
            );
        } catch (UnauthorizedFeedbackFailure) {
            abort(404);
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['recipient' => $e->getMessage()]], 422);
            }

            return back()->withErrors(['recipient' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $feedback], 201);
        }

        return redirect()->route('student.feedback.index')->with('status', 'Feedback sent.');
    }
}
