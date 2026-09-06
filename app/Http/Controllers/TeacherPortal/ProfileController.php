<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Http\Controllers\Controller;
use App\Models\StaffDocument;
use App\Models\StaffProfile;
use App\Repositories\StaffProfileRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $teacher = $request->user();

        return view('teacher.profile.show', [
            'teacher' => $teacher->load('school', 'role'),
            'staffProfile' => StaffProfile::where('user_id', $teacher->id)->first(),
            'documents' => StaffDocument::where('user_id', $teacher->id)->orderByDesc('created_at')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $teacher = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required_without:mobile_no',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($teacher->id),
            ],
            'mobile_no' => [
                'required_without:email',
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'mobile_no')->ignore($teacher->id),
            ],
            'photo_path' => ['nullable', 'string', 'max:255'],
        ]);

        $teacher->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'mobile_no' => $data['mobile_no'] ?? null,
            'photo_path' => $data['photo_path'] ?? null,
        ]);

        return back()->with('status', 'Profile updated.');
    }

    /**
     * Qualifications/responsibilities — the richer profile fields update()
     * above deliberately doesn't touch, since those stay on `users`
     * while these live on the peer staff_profiles row (see that
     * migration's doc comment). Qualifications arrive as newline-
     * separated text from the form and are split into a list here,
     * rather than asking the browser to submit JSON directly.
     */
    public function updateProfile(Request $request, StaffProfileRepository $repository): RedirectResponse
    {
        $teacher = $request->user();

        $data = $request->validate([
            'qualifications' => ['nullable', 'string', 'max:4000'],
            'responsibilities' => ['nullable', 'string', 'max:4000'],
        ]);

        $qualifications = collect(preg_split('/\r\n|\r|\n/', $data['qualifications'] ?? ''))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        $repository->updateProfile($teacher->school_id, $teacher, [
            'qualifications' => $qualifications,
            'responsibilities' => $data['responsibilities'] ?? null,
        ]);

        return back()->with('status', 'Profile details updated.');
    }

    public function uploadCv(Request $request, StaffProfileRepository $repository): RedirectResponse
    {
        $teacher = $request->user();

        $request->validate([
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:4096'],
        ]);

        $path = $request->file('cv')->store('staff-cvs', 'local');

        $repository->uploadCv($teacher->school_id, $teacher, $path);

        return back()->with('status', 'CV uploaded.');
    }

    public function storeDocument(Request $request, StaffProfileRepository $repository): RedirectResponse
    {
        $teacher = $request->user();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:4096'],
        ]);

        $path = $request->file('document')->store('staff-documents', 'local');

        $repository->addDocument($teacher->school_id, $teacher, $data['label'], $path, $teacher);

        return back()->with('status', 'Document uploaded.');
    }

    /**
     * $document has no 'scope.checked' route-model resolution — it
     * carries a route parameter so the route-table lint (Phase1TestGateTest
     * row 8) still requires the middleware, but the actual ownership
     * check is StaffProfileRepository::deleteDocument()'s own
     * school_id + user_id match, the same "controller resolves via a
     * repository-level check, not ScopeService" shape
     * ExamMarkRepository's callers already use.
     */
    public function destroyDocument(Request $request, StaffProfileRepository $repository, int $document): RedirectResponse
    {
        $teacher = $request->user();

        try {
            $repository->deleteDocument($teacher->school_id, $teacher, $document);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        return back()->with('status', 'Document removed.');
    }

    public function downloadDocument(Request $request, int $document): Response
    {
        $teacher = $request->user();

        $staffDocument = StaffDocument::where('school_id', $teacher->school_id)
            ->where('user_id', $teacher->id)
            ->find($document);

        if ($staffDocument === null || ! Storage::disk('local')->exists($staffDocument->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($staffDocument->file_path, $staffDocument->label);
    }

    public function acknowledgeRules(Request $request, StaffProfileRepository $repository): RedirectResponse
    {
        $teacher = $request->user();

        $repository->acknowledgeRules($teacher->school_id, $teacher);

        return back()->with('status', 'School rules acknowledged.');
    }
}
