@extends('layouts.app')

@section('title', 'School setup — SchoolOS')
@section('page-title', 'School setup')

@section('content')
    @if ($errors->any())
        <x-alert variant="error">{{ $errors->first() }}</x-alert>
    @endif

    @if ($school->setup_status !== 'complete')
        <x-card :padded="true" class="mb-4 border-indigo-200 bg-indigo-50">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-indigo-900">Finish setting up your school</p>
                    <p class="mt-0.5 text-sm text-indigo-700">A few quick steps left — academic years, subjects, and your first staff/students.</p>
                </div>
                <a href="{{ route('admin.setup.index') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Continue setup
                </a>
            </div>
        </x-card>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        {{-- Academic years --}}
        <x-card id="academic-years">
            <h2 class="text-sm font-semibold text-gray-900">Academic years</h2>

            @if ($academicYears->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No academic years yet.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach ($academicYears as $academicYear)
                        <li class="flex items-center justify-between py-2">
                            <span class="text-gray-900">{{ $academicYear->label }}</span>
                            @if ($academicYear->is_current)
                                <x-badge variant="success">Current</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.academic-years.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-input name="label" label="Label" placeholder="2026-2027" required />
                <label class="flex items-center gap-2 pb-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_current" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                    Set as current
                </label>
                <x-button type="submit" size="sm">Add</x-button>
            </form>
        </x-card>

        {{-- Standards --}}
        <x-card id="standards">
            <h2 class="text-sm font-semibold text-gray-900">Standards</h2>

            @if ($standards->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No standards yet.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach ($standards as $standard)
                        <li class="py-2 text-gray-900">{{ $standard->name }}</li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.standards.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-input name="name" label="Name" placeholder="Grade 5" required />
                <x-button type="submit" size="sm">Add</x-button>
            </form>
        </x-card>

        {{-- Sections --}}
        <x-card id="sections">
            <h2 class="text-sm font-semibold text-gray-900">Sections</h2>

            @if ($sections->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No sections yet.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach ($sections as $section)
                        <li class="py-2 text-gray-900">{{ $section->name }}</li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.sections.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-input name="name" label="Name" placeholder="A" required />
                <x-button type="submit" size="sm">Add</x-button>
            </form>
        </x-card>

        {{-- Subjects --}}
        <x-card id="subjects">
            <h2 class="text-sm font-semibold text-gray-900">Subjects</h2>

            @if ($subjects->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No subjects yet.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach ($subjects as $subject)
                        <li class="flex items-center justify-between py-2">
                            <span class="text-gray-900">{{ $subject->name }}</span>
                            @if ($subject->code)
                                <span class="text-gray-400">{{ $subject->code }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.subjects.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-input name="name" label="Name" placeholder="Mathematics" required />
                <x-input name="code" label="Code" placeholder="MATH" />
                <x-button type="submit" size="sm">Add</x-button>
            </form>
        </x-card>

        {{-- Class sections: creation form, plus per-row "assign class teacher" /
             "assign subject teacher" dialogs (admin.class-sections.assign-teacher,
             admin.class-sections.teacher-assignments.store). --}}
        <x-card id="class-sections">
            <h2 class="text-sm font-semibold text-gray-900">Class sections</h2>

            @if ($classSections->isEmpty())
                <p class="mt-2 text-sm text-gray-500">
                    No class sections yet. Create an academic year, a standard, and a section above first.
                </p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach ($classSections as $classSection)
                        <li class="flex items-center justify-between gap-2 py-2 text-gray-900">
                            <span>
                                {{ $classSection->standard?->name }} {{ $classSection->section?->name }}
                                <span class="text-gray-400">— {{ $classSection->academicYear?->label }}</span>
                                <span class="text-gray-400">— {{ $classSection->classTeacher?->name ?? 'No teacher assigned' }}</span>
                            </span>
                            <span class="flex shrink-0 gap-1.5">
                                <x-button type="button" size="sm" variant="secondary" data-dialog-open="assign-class-teacher-{{ $classSection->id }}">
                                    Assign class teacher
                                </x-button>
                                <x-button type="button" size="sm" variant="secondary" data-dialog-open="assign-subject-teacher-{{ $classSection->id }}">
                                    Assign subject teacher
                                </x-button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.class-sections.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-select
                    name="academic_year_id"
                    label="Academic year"
                    :options="$academicYears->pluck('label', 'id')"
                    :use-old="false"
                    required
                />
                <x-select
                    name="standard_id"
                    label="Standard"
                    :options="$standards->pluck('name', 'id')"
                    required
                />
                <x-select
                    name="section_id"
                    label="Section"
                    :options="$sections->pluck('name', 'id')"
                    required
                />
                <x-select
                    name="class_teacher_id"
                    label="Class teacher"
                    :options="$teachers->pluck('name', 'id')"
                    required
                />
                <x-button type="submit" size="sm">Create class section</x-button>
            </form>
        </x-card>

        {{-- Staff account creation (admin.staff.store) --}}
        <x-card id="create-staff-account">
            <h2 class="text-sm font-semibold text-gray-900">Create staff account</h2>

            <form method="POST" action="{{ route('admin.staff.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @csrf
                <x-input name="name" label="Name" required />
                <x-input type="email" name="email" label="Email" />
                <x-input name="mobile_no" label="Mobile" />
                <x-select
                    name="role"
                    label="Role"
                    :options="[
                        'teacher' => 'Teacher',
                        'accountant' => 'Accountant',
                        'librarian' => 'Librarian',
                        'receptionist' => 'Receptionist',
                        'staff' => 'Staff',
                    ]"
                    required
                />
                <x-input type="password" name="password" label="Password (leave blank to invite instead)" />
                <div class="sm:col-span-2 lg:col-span-5">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="invite" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        Send an activation email instead of setting a password
                    </label>
                </div>
                <div class="sm:col-span-2 lg:col-span-5">
                    <x-button type="submit" size="sm">Create account</x-button>
                </div>
            </form>
        </x-card>

        {{-- Direct student registration (admin.students.store) —
             SchoolOS Account Creation & Onboarding plan, §5. Distinct
             from "Enroll a student" below, which only enrolls an
             already-existing student user; this creates the student
             record itself, for a walk-in registration that skips the
             public admission form. --}}
        <x-card id="register-a-student">
            <h2 class="text-sm font-semibold text-gray-900">Register a new student</h2>
            <p class="mt-1 text-xs text-gray-500">
                Creates the student record and enrolls them in one step. The student gets no portal login
                by default — use "Enable portal login" below when they need one.
            </p>

            <form method="POST" action="{{ route('admin.students.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @csrf
                <x-input name="name" label="Name" required />
                <x-input type="email" name="email" label="Email" />
                <x-input name="mobile_no" label="Mobile" />
                <x-select
                    name="academic_year_id"
                    label="Academic year"
                    :options="$academicYears->pluck('label', 'id')"
                    :use-old="false"
                    required
                />
                <x-select
                    name="class_section_id"
                    label="Class section"
                    :options="$classSections->mapWithKeys(fn ($cs) => [$cs->id => trim(($cs->standard?->name ?? '').' '.($cs->section?->name ?? ''))])"
                    :use-old="false"
                    required
                />
                <x-input name="roll_number" label="Roll number" placeholder="R1" required />
                <div class="sm:col-span-2 lg:col-span-5">
                    <x-button type="submit" size="sm">Register student</x-button>
                </div>
            </form>

            @if ($students->isNotEmpty())
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="text-xs font-medium text-gray-500">Enable portal login</p>
                    <ul class="mt-2 divide-y divide-gray-100 text-sm">
                        @foreach ($students as $student)
                            <li class="flex items-center justify-between py-1.5">
                                <span class="text-gray-900">{{ $student->name }}</span>
                                <div class="flex items-center gap-2">
                                    <x-badge :variant="$student->status === 'active' ? 'success' : 'neutral'">
                                        {{ ucfirst($student->status) }}
                                    </x-badge>
                                    @if ($student->status === 'invited')
                                        <form method="POST" action="{{ route('admin.students.invite', $student) }}">
                                            @csrf
                                            <x-button type="submit" size="sm" variant="ghost">Invite</x-button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-card>

        {{-- Student enrollment (admin.enrollments.store) --}}
        <x-card id="enroll-a-student">
            <h2 class="text-sm font-semibold text-gray-900">Enroll a student</h2>

            <form method="POST" action="{{ route('admin.enrollments.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <x-select
                    name="academic_year_id"
                    label="Academic year"
                    :options="$academicYears->pluck('label', 'id')"
                    :use-old="false"
                    required
                />
                <x-select
                    name="class_section_id"
                    label="Class section"
                    :options="$classSections->mapWithKeys(fn ($cs) => [$cs->id => trim(($cs->standard?->name ?? '').' '.($cs->section?->name ?? ''))])"
                    :use-old="false"
                    required
                />
                <x-select
                    name="student_id"
                    label="Student"
                    :options="$students->pluck('name', 'id')"
                    :use-old="false"
                    required
                />
                <x-input name="roll_number" label="Roll number" placeholder="R1" required />
                <x-button type="submit" size="sm">Enroll</x-button>
            </form>
        </x-card>

        {{-- Parent account creation + linking (admin.parents.store) --}}
        <x-card id="create-parent-account">
            <h2 class="text-sm font-semibold text-gray-900">Create parent account</h2>

            <form method="POST" action="{{ route('admin.parents.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @csrf
                <x-input name="name" label="Name" required />
                <x-input type="email" name="email" label="Email" />
                <x-input name="mobile_no" label="Mobile" />
                <x-input type="password" name="password" label="Password (leave blank to invite instead)" />
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="invite" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                    Send an activation email instead of setting a password
                </label>
                <div>
                    <label for="student_ids" class="mb-1 block text-sm font-medium text-gray-700">
                        Students <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="student_ids"
                        name="student_ids[]"
                        multiple
                        required
                        class="block min-h-24 w-full rounded-md border-0 py-1.5 pl-2.5 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                    >
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" @selected(in_array((string) $student->id, old('student_ids', []), true))>
                                {{ $student->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('student_ids')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="sm:col-span-2 lg:col-span-5">
                    <x-button type="submit" size="sm">Create and link</x-button>
                </div>
            </form>
        </x-card>

        {{-- Parent-student links (admin.parent-links.store / .destroy) --}}
        <x-card id="parent-links">
            <h2 class="text-sm font-semibold text-gray-900">Parent links</h2>

            @if ($parentLinks->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No parent links yet.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach ($parentLinks as $parentLink)
                        <li class="flex items-center justify-between py-2">
                            <span class="text-gray-900">
                                {{ $parentLink->parent?->name }}
                                <span class="text-gray-400">→</span>
                                {{ $parentLink->student?->name }}
                            </span>
                            <form method="POST" action="{{ route('admin.parent-links.destroy', $parentLink) }}">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" size="sm" variant="ghost">Unlink</x-button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.parent-links.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-select
                    name="parent_id"
                    label="Parent"
                    :options="$parents->pluck('name', 'id')"
                    :use-old="false"
                    required
                />
                <x-select
                    name="student_id"
                    label="Student"
                    :options="$students->pluck('name', 'id')"
                    :use-old="false"
                    required
                />
                <x-button type="submit" size="sm">Link parent</x-button>
            </form>
        </x-card>

        {{-- Self-service parent link requests awaiting approval
             (admin.parent-links.approve / .reject) — SchoolOS Account
             Creation & Onboarding plan, §4. --}}
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Pending parent link requests</h2>

            @if ($pendingParentLinkRequests->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No pending requests.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach ($pendingParentLinkRequests as $pendingRequest)
                        <li class="flex items-center justify-between py-2">
                            <span class="text-gray-900">
                                {{ $pendingRequest->parent?->name }}
                                <span class="text-gray-400">→</span>
                                {{ $pendingRequest->student?->name }}
                            </span>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.parent-links.approve', $pendingRequest) }}">
                                    @csrf
                                    <x-button type="submit" size="sm">Approve</x-button>
                                </form>
                                <form method="POST" action="{{ route('admin.parent-links.reject', $pendingRequest) }}">
                                    @csrf
                                    <x-button type="submit" size="sm" variant="ghost">Reject</x-button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Pending lesson plans awaiting review (admin.lesson-plans.approve / .reject) --}}
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Pending lesson plans</h2>

            @if ($pendingLessonPlans->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No lesson plans awaiting review.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach ($pendingLessonPlans as $lessonPlan)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <span class="text-gray-900">
                                {{ $lessonPlan->title }}
                                <span class="text-gray-400">
                                    — {{ $lessonPlan->teacher?->name }}
                                    — {{ $lessonPlan->subject?->name }}
                                    — {{ trim(($lessonPlan->classSection?->standard?->name ?? '').' '.($lessonPlan->classSection?->section?->name ?? '')) }}
                                </span>
                            </span>
                            <span class="flex shrink-0 gap-1.5">
                                <form method="POST" action="{{ route('admin.lesson-plans.approve', $lessonPlan) }}">
                                    @csrf
                                    <x-button type="submit" size="sm" variant="secondary">Approve</x-button>
                                </form>
                                <x-button type="button" size="sm" variant="ghost" data-dialog-open="reject-lesson-plan-{{ $lessonPlan->id }}">
                                    Reject
                                </x-button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Announcements (admin.announcements.store). Submission is
             JS-driven (see script at the end of this section) because
             Admin\AnnouncementController::store() is a JSON-only endpoint
             by design — see that controller's doc comment — so a plain
             form POST would navigate the browser to a raw JSON response
             instead of back to this dashboard. --}}
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Post an announcement</h2>

            <div id="admin-announcement-alert" class="mb-3"></div>

            <form id="admin-announcement-form" method="POST" action="{{ route('admin.announcements.store') }}" class="mt-3 space-y-3">
                @csrf
                <x-input name="title" label="Title" required />
                <div>
                    <label for="body" class="mb-1 block text-sm font-medium text-gray-700">
                        Body <span class="text-red-500">*</span>
                    </label>
                    <textarea id="body" name="body" rows="3" required class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>
                <x-select
                    name="audience_type"
                    label="Audience"
                    :options="['school' => 'Whole school', 'class_section' => 'One class section']"
                    required
                />
                <x-select
                    name="class_section_id"
                    label="Class section (if applicable)"
                    :options="$classSections->mapWithKeys(fn ($cs) => [$cs->id => trim(($cs->standard?->name ?? '').' '.($cs->section?->name ?? ''))])"
                    :use-old="false"
                />
                <x-button type="submit" size="sm">Post announcement</x-button>
            </form>
        </x-card>
    </div>

    <script>
        // Admin "Post an announcement" form: AnnouncementController::store()
        // is JSON-only by design (success -> 201 JSON, validation failure ->
        // 422 JSON — see the controller's doc comment), so this intercepts
        // the plain POST form and submits it via fetch() instead, rendering
        // the result inline rather than navigating to the raw JSON body.
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('admin-announcement-form');
            const alertContainer = document.getElementById('admin-announcement-alert');
            if (!form || !alertContainer) return;

            const ERROR_RING = ['ring-red-300', 'focus:ring-red-500'];
            const DEFAULT_RING = ['ring-gray-300', 'focus:ring-indigo-600'];

            function clearAlert() {
                alertContainer.innerHTML = '';
            }

            function clearFieldErrors() {
                form.querySelectorAll('[data-js-field-error]').forEach((el) => el.remove());
                form.querySelectorAll('[data-js-error-target]').forEach((field) => {
                    field.classList.remove(...ERROR_RING);
                    field.classList.add(...DEFAULT_RING);
                    field.removeAttribute('data-js-error-target');
                });
            }

            // Same visual shape as the alert component: icon + message,
            // ring-inset, rounded-md — rebuilt as plain markup since this
            // is JS-driven rather than a Blade component render.
            function showAlert(variant, message) {
                clearAlert();

                const isSuccess = variant === 'success';
                const colorClasses = isSuccess
                    ? 'bg-green-50 text-green-800 ring-green-600/20'
                    : 'bg-red-50 text-red-800 ring-red-600/20';
                const iconPath = isSuccess
                    ? 'M16.7 5.3a1 1 0 010 1.4l-7 7a1 1 0 01-1.4 0l-3-3a1 1 0 111.4-1.4l2.3 2.29 6.3-6.3a1 1 0 011.4 0z'
                    : 'M10 2a8 8 0 100 16 8 8 0 000-16zM9 6h2v6H9V6zm0 8h2v2H9v-2z';

                const wrapper = document.createElement('div');
                wrapper.className = `flex items-start gap-2.5 rounded-md p-3 text-sm ring-1 ring-inset ${colorClasses}`;
                wrapper.setAttribute('role', 'status');

                const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                icon.setAttribute('class', 'mt-0.5 h-4 w-4 shrink-0');
                icon.setAttribute('viewBox', '0 0 20 20');
                icon.setAttribute('fill', 'currentColor');
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', iconPath);
                icon.appendChild(path);

                const messageEl = document.createElement('div');
                messageEl.className = 'flex-1';
                messageEl.textContent = message;

                wrapper.appendChild(icon);
                wrapper.appendChild(messageEl);
                alertContainer.appendChild(wrapper);
            }

            // Matches the input/select components' own error-state
            // rendering: a red ring on the field plus a
            // `mt-1 text-sm text-red-600` message right after it.
            function showFieldError(fieldName, message) {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (!field) return;

                field.setAttribute('data-js-error-target', '');
                field.classList.remove(...DEFAULT_RING);
                field.classList.add(...ERROR_RING);

                const error = document.createElement('p');
                error.className = 'mt-1 text-sm text-red-600';
                error.setAttribute('data-js-field-error', '');
                error.textContent = message;
                field.insertAdjacentElement('afterend', error);
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                clearAlert();
                clearFieldErrors();

                const token = form.querySelector('input[name="_token"]')?.value ?? '';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: new FormData(form),
                    });

                    if (response.status === 201) {
                        form.reset();
                        showAlert('success', 'Announcement posted.');
                        return;
                    }

                    if (response.status === 422) {
                        const payload = await response.json();
                        const errors = payload.errors || {};
                        const fieldNames = Object.keys(errors);
                        const firstMessage = fieldNames.length
                            ? [].concat(errors[fieldNames[0]])[0]
                            : 'Please check the form and try again.';

                        showAlert('error', firstMessage);
                        fieldNames.forEach((fieldName) => {
                            showFieldError(fieldName, [].concat(errors[fieldName])[0]);
                        });
                        return;
                    }

                    showAlert('error', 'Something went wrong. Please try again.');
                } catch (error) {
                    showAlert('error', 'Something went wrong. Please try again.');
                }
            });
        });
    </script>

    {{-- One "assign class teacher" / "assign subject teacher" dialog pair per class section --}}
    @foreach ($classSections as $classSection)
        <x-dialog id="assign-class-teacher-{{ $classSection->id }}" title="Assign class teacher">
            <form method="POST" action="{{ route('admin.class-sections.assign-teacher', $classSection) }}" class="space-y-4">
                @csrf
                <x-select
                    name="teacher_id"
                    label="Teacher"
                    :options="$teachers->pluck('name', 'id')"
                    :use-old="false"
                    required
                />
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                    <x-button type="button" variant="ghost" data-dialog-close>Cancel</x-button>
                    <x-button type="submit" variant="primary">Assign</x-button>
                </div>
            </form>
        </x-dialog>

        <x-dialog id="assign-subject-teacher-{{ $classSection->id }}" title="Assign subject teacher">
            <form method="POST" action="{{ route('admin.class-sections.teacher-assignments.store', $classSection) }}" class="space-y-4">
                @csrf
                <x-select
                    name="subject_id"
                    label="Subject"
                    :options="$subjects->pluck('name', 'id')"
                    :use-old="false"
                    required
                />
                <x-select
                    name="teacher_id"
                    label="Teacher"
                    :options="$teachers->pluck('name', 'id')"
                    :use-old="false"
                    required
                />
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                    <x-button type="button" variant="ghost" data-dialog-close>Cancel</x-button>
                    <x-button type="submit" variant="primary">Assign</x-button>
                </div>
            </form>
        </x-dialog>
    @endforeach

    {{-- One "reject lesson plan" dialog per pending lesson plan --}}
    @foreach ($pendingLessonPlans as $lessonPlan)
        <x-dialog id="reject-lesson-plan-{{ $lessonPlan->id }}" title="Reject lesson plan — {{ $lessonPlan->title }}">
            <form method="POST" action="{{ route('admin.lesson-plans.reject', $lessonPlan) }}" class="space-y-4">
                @csrf
                <div>
                    <label for="reason-{{ $lessonPlan->id }}" class="mb-1 block text-sm font-medium text-gray-700">
                        Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea id="reason-{{ $lessonPlan->id }}" name="reason" rows="3" required class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600"></textarea>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                    <x-button type="button" variant="ghost" data-dialog-close>Cancel</x-button>
                    <x-button type="submit" variant="primary">Reject</x-button>
                </div>
            </form>
        </x-dialog>
    @endforeach
@endsection
