<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.school-profile.edit', [
            'school' => $request->user()->school,
            'schoolTypes' => $this->schoolTypes(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $data = $request->validate($this->rules($school->id));
        $beforeState = $school->only(['name', 'initials', 'email', 'phone', 'location', 'google_maps_url', 'school_type', 'logo_path', 'fee_overdue_reminder_days']);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('school-logos', 'public');
        }

        unset($data['logo']);

        $school->update($data);

        AuditLog::create([
            'school_id' => $school->id,
            'actor_id' => $request->user()->id,
            'action' => 'school.profile_updated',
            'entity_type' => 'School',
            'entity_id' => $school->id,
            'before_state' => $beforeState,
            'after_state' => $school->fresh()->only(['name', 'initials', 'email', 'phone', 'location', 'google_maps_url', 'school_type', 'logo_path', 'fee_overdue_reminder_days']),
        ]);

        return back()->with('status', 'School profile updated.');
    }

    private function rules(int $schoolId): array
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
            'fee_overdue_reminder_days' => ['required', 'integer', 'min:1', 'max:365'],
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
