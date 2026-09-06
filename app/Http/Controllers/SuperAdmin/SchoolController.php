<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(): View
    {
        $schools = School::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return view('super-admin.schools.index', [
            'schools' => $schools,
            'schoolTypes' => $this->schoolTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('school-logos', 'public');
        }

        unset($data['logo']);

        $school = School::create($data);

        AuditLog::create([
            'school_id' => $school->id,
            'actor_id' => $request->user()->id,
            'action' => 'school.created',
            'entity_type' => 'School',
            'entity_id' => $school->id,
            'before_state' => null,
            'after_state' => $school->only(['name', 'initials', 'email', 'phone', 'location', 'google_maps_url', 'school_type', 'logo_path', 'status']),
        ]);

        return redirect()
            ->route('super-admin.schools.show', $school)
            ->with('status', 'School created.');
    }

    public function show(School $school): View
    {
        $school->load([
            'users' => fn ($query) => $query
                ->with('role')
                ->whereHas('role', fn ($roleQuery) => $roleQuery->where('key', 'school_admin'))
                ->orderBy('name'),
        ]);

        $auditLogs = AuditLog::query()
            ->with('actor')
            ->where('school_id', $school->id)
            ->latest()
            ->limit(20)
            ->get();

        return view('super-admin.schools.show', [
            'school' => $school,
            'schoolAdmins' => $school->users,
            'auditLogs' => $auditLogs,
            'schoolTypes' => $this->schoolTypes(),
        ]);
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate([
            ...$this->rules($school->id),
        ]);

        $beforeState = $school->only(['name', 'initials', 'email', 'phone', 'location', 'google_maps_url', 'school_type', 'logo_path']);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('school-logos', 'public');
        }

        unset($data['logo']);

        $school->update($data);

        AuditLog::create([
            'school_id' => $school->id,
            'actor_id' => $request->user()->id,
            'action' => 'school.updated',
            'entity_type' => 'School',
            'entity_id' => $school->id,
            'before_state' => $beforeState,
            'after_state' => $school->fresh()->only(['name', 'initials', 'email', 'phone', 'location', 'google_maps_url', 'school_type', 'logo_path']),
        ]);

        return back()->with('status', 'School updated.');
    }

    public function updateStatus(Request $request, School $school, AuthenticationService $auth): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        if ($school->status === $data['status']) {
            return back()->with('status', 'School status is already ' . $data['status'] . '.');
        }

        $auth->setSchoolStatus($school->id, $data['status'], $request->user());

        return back()->with('status', 'School status updated.');
    }

    private function rules(?int $schoolId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('schools', 'name')->ignore($schoolId)],
            'initials' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', Rule::unique('schools', 'email')->ignore($schoolId)],
            'phone' => ['required', 'string', 'max:255', Rule::unique('schools', 'phone')->ignore($schoolId)],
            'location' => ['nullable', 'string', 'max:255'],
            'google_maps_url' => ['nullable', 'url', 'max:255'],
            'school_type' => ['nullable', Rule::in(array_keys($this->schoolTypes()))],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    private function schoolTypes(): array
    {
        return [
            '' => 'Not set',
            'creche' => 'Creche',
            'primary' => 'Primary',
            'secondary' => 'Secondary',
            'college_tertiary' => 'College / Tertiary',
            'mixed' => 'Mixed',
        ];
    }
}
