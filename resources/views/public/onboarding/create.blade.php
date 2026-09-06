<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Create your school - SchoolOS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="schoolos-public min-h-screen bg-schoolos-soft text-[#171717] antialiased">
@php
    $fieldClass = 'mt-2 min-h-12 w-full rounded-md border border-gray-200 bg-white px-3 py-3 text-sm text-gray-950 shadow-sm outline-none transition focus:border-green-500 focus:ring-4 focus:ring-green-100';
    $labelClass = 'text-sm font-semibold text-gray-900';
    $errorClass = 'mt-1 text-sm text-red-600';
    $moduleLabels = [
        'student_information' => ['Student Information System', 'Manage student records'],
        'attendance' => ['Attendance Management', 'Track attendance easily'],
        'academics' => ['Academics & Grades', 'Manage classes, subjects and grades'],
        'fees' => ['Fees & Invoicing', 'Fee collection and invoices'],
        'reports' => ['Reports & Analytics', 'View useful school reports'],
        'library' => ['Library Management', 'Manage library resources'],
        'inventory' => ['Inventory Management', 'Track school inventory'],
    ];
    $oldModules = old('enabled_modules', $moduleKeys);
    $oldLevels = old('education_levels', []);
    $oldDays = old('working_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
@endphp

<main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-5 sm:px-6 lg:px-8">
    <header class="flex items-center justify-between">
        <x-schoolos-logo />
        <div class="flex items-center gap-3">
            <x-theme-toggle />
            <a href="{{ route('login') }}" class="text-sm font-semibold text-green-700 hover:text-green-800">Sign in</a>
        </div>
    </header>

    <div class="mt-6 grid flex-1 gap-6 lg:grid-cols-[280px_minmax(0,1fr)] lg:items-start">
        <aside class="hidden rounded-lg border border-green-100 bg-white p-5 shadow-sm lg:block">
            <p class="text-sm font-semibold text-gray-950">Onboarding Progress</p>
            <ol class="mt-6 space-y-4" data-progress-list>
                @foreach (['School Information', 'Academic Setup', 'Modules & Features', 'Admin Account'] as $index => $step)
                    <li class="flex items-center gap-3 text-sm" data-progress-item="{{ $index + 1 }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full border text-xs font-semibold">○</span>
                        <span>{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
            <div class="mt-8 rounded-md bg-green-50 p-4 text-sm text-green-900">
                {{ $trialDays }}-day trial is available. You can also mark the school as paid during signup.
            </div>
        </aside>

        <section class="rounded-lg border border-green-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 p-5 lg:p-7">
                <div class="lg:hidden">
                    <div class="flex items-center justify-between text-sm font-semibold">
                        <span data-mobile-step-label>Step 1 of 4</span>
                        <span class="text-green-700" data-mobile-step-title>School Information</span>
                    </div>
                    <div class="mt-3 h-2 rounded-full bg-gray-100">
                        <div class="h-2 rounded-full bg-green-600 transition-all" style="width: 25%" data-mobile-progress></div>
                    </div>
                </div>
                <div class="hidden lg:block">
                    <p class="text-sm font-semibold text-green-700">Create your school account</p>
                    <h1 class="mt-2 text-2xl font-bold text-gray-950">Set up SchoolOS</h1>
                </div>
            </div>

            @if ($errors->any())
                <div class="mx-5 mt-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 lg:mx-7">
                    Please review the highlighted fields and try again.
                </div>
            @endif

            <form method="POST" action="{{ route('public.onboarding.store') }}" class="p-5 pb-28 lg:p-7 lg:pb-7" data-onboarding-form data-loading-text="Creating School...">
                @csrf

                <section data-step="1" data-step-title="School Information">
                    <h2 class="text-2xl font-bold text-gray-950">School Information</h2>
                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="school_name" class="{{ $labelClass }}">School Name</label>
                            <input id="school_name" name="school_name" type="text" value="{{ old('school_name') }}" placeholder="Enter school name" class="{{ $fieldClass }}" required aria-describedby="@error('school_name') school_name_error @enderror">
                            @error('school_name') <p id="school_name_error" class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="school_type" class="{{ $labelClass }}">School Type</label>
                            <select id="school_type" name="school_type" class="{{ $fieldClass }}" required>
                                @foreach ($schoolTypes as $key => $label)
                                    <option value="{{ $key }}" @selected(old('school_type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('school_type') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="school_email" class="{{ $labelClass }}">School Email</label>
                            <input id="school_email" name="school_email" type="email" value="{{ old('school_email') }}" placeholder="school@example.com" class="{{ $fieldClass }}" required>
                            @error('school_email') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="school_phone" class="{{ $labelClass }}">School Phone</label>
                            <input id="school_phone" name="school_phone" type="tel" value="{{ old('school_phone') }}" placeholder="+234..." class="{{ $fieldClass }}" required>
                            @error('school_phone') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="country" class="{{ $labelClass }}">Country</label>
                            <select id="country" name="country" class="{{ $fieldClass }}" required>
                                <option value="Nigeria" @selected(old('country', 'Nigeria') === 'Nigeria')>Nigeria</option>
                            </select>
                            @error('country') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="state" class="{{ $labelClass }}">State</label>
                            <select id="state" name="state" class="{{ $fieldClass }}" required>
                                @foreach (['Abia','Abuja FCT','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba','Yobe','Zamfara'] as $state)
                                    <option value="{{ $state }}" @selected(old('state') === $state)>{{ $state }}</option>
                                @endforeach
                            </select>
                            @error('state') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="city" class="{{ $labelClass }}">City</label>
                            <input id="city" name="city" type="text" value="{{ old('city') }}" placeholder="Enter city" class="{{ $fieldClass }}" required>
                            @error('city') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label for="address" class="{{ $labelClass }}">Address</label>
                            <textarea id="address" name="address" rows="4" class="{{ $fieldClass }}" required>{{ old('address') }}</textarea>
                            @error('address') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section data-step="2" data-step-title="Academic Setup" class="hidden">
                    <h2 class="text-2xl font-bold text-gray-950">Academic Setup</h2>
                    <p class="mt-2 text-sm text-gray-600">Define your academic structure.</p>
                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="academic_year_label" class="{{ $labelClass }}">Academic Session</label>
                            <select id="academic_year_label" name="academic_year_label" class="{{ $fieldClass }}" required>
                                @foreach (['2024/2025', '2025/2026', '2026/2027', '2027/2028'] as $session)
                                    <option value="{{ $session }}" @selected(old('academic_year_label', '2026/2027') === $session)>{{ $session }}</option>
                                @endforeach
                            </select>
                            @error('academic_year_label') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="grading_system" class="{{ $labelClass }}">Grading System</label>
                            <select id="grading_system" name="grading_system" class="{{ $fieldClass }}" required>
                                @foreach ($gradingSystems as $key => $label)
                                    <option value="{{ $key }}" @selected(old('grading_system', 'percentage') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('grading_system') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <fieldset class="md:col-span-2">
                            <legend class="{{ $labelClass }}">Select Classes</legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach ($educationLevels as $key => $label)
                                    <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-md border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 transition has-checked:border-green-300 has-checked:bg-green-50">
                                        <input type="checkbox" name="education_levels[]" value="{{ $key }}" @checked(in_array($key, $oldLevels, true)) class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('education_levels') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </fieldset>
                        <div>
                            <label for="school_week" class="{{ $labelClass }}">School Week</label>
                            <select id="school_week" class="{{ $fieldClass }}" data-school-week>
                                <option value="weekday">Monday - Friday</option>
                                <option value="saturday">Monday - Saturday</option>
                            </select>
                            <div data-working-days>
                                @foreach ($workingDays as $key => $label)
                                    <input type="checkbox" name="working_days[]" value="{{ $key }}" @checked(in_array($key, $oldDays, true)) class="hidden">
                                @endforeach
                            </div>
                            @error('working_days') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div class="rounded-md border border-gray-200 p-4">
                            <label class="flex cursor-pointer items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-950">Allow manual promotion</span>
                                    <span class="mt-1 block text-sm text-gray-600">Allow promoting students manually</span>
                                </span>
                                <input type="hidden" name="allow_manual_promotion" value="0">
                                <input type="checkbox" name="allow_manual_promotion" value="1" @checked(old('allow_manual_promotion', '1') === '1') class="peer sr-only">
                                <span class="h-7 w-12 rounded-full bg-gray-200 p-1 transition peer-checked:bg-green-600 peer-checked:[&>span]:translate-x-5"><span class="block h-5 w-5 rounded-full bg-white shadow transition"></span></span>
                            </label>
                        </div>
                    </div>
                </section>

                <section data-step="3" data-step-title="Modules & Features" class="hidden">
                    <h2 class="text-2xl font-bold text-gray-950">Modules & Features</h2>
                    <p class="mt-2 text-sm text-gray-600">Choose the modules your school needs.</p>
                    <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($moduleLabels as $key => [$title, $description])
                            <label class="cursor-pointer rounded-md border border-gray-200 bg-white p-4 transition has-checked:border-green-300 has-checked:bg-green-50">
                                <span class="flex items-start gap-3">
                                    <input type="checkbox" name="enabled_modules[]" value="{{ $key }}" @checked(in_array($key, $oldModules, true)) class="mt-1 h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-950">{{ $title }}</span>
                                        <span class="mt-1 block text-sm text-gray-600">{{ $description }}</span>
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('enabled_modules') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror

                    <div class="mt-6 max-w-md">
                        <label for="attendance_config" class="{{ $labelClass }}">Who can take attendance?</label>
                        <select id="attendance_config" class="{{ $fieldClass }}" disabled>
                            <option>Any assigned teacher</option>
                        </select>
                    </div>
                </section>

                <section data-step="4" data-step-title="Admin Account" class="hidden">
                    <h2 class="text-2xl font-bold text-gray-950">Admin Account</h2>
                    <p class="mt-2 text-sm text-gray-600">Create the first admin account.</p>
                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="{{ $labelClass }}">Plan</label>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="cursor-pointer rounded-md border border-gray-200 p-4 has-checked:border-green-300 has-checked:bg-green-50">
                                    <input type="radio" name="billing_plan" value="trial" @checked(old('billing_plan', 'trial') === 'trial') class="h-4 w-4 text-green-600 focus:ring-green-500">
                                    <span class="ml-2 text-sm font-semibold text-gray-950">{{ $trialDays }}-day demo</span>
                                </label>
                                <label class="cursor-pointer rounded-md border border-gray-200 p-4 has-checked:border-green-300 has-checked:bg-green-50">
                                    <input type="radio" name="billing_plan" value="paid" @checked(old('billing_plan') === 'paid') class="h-4 w-4 text-green-600 focus:ring-green-500">
                                    <span class="ml-2 text-sm font-semibold text-gray-950">Pay now</span>
                                </label>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label for="admin_name" class="{{ $labelClass }}">Full Name</label>
                            <input id="admin_name" name="admin_name" type="text" value="{{ old('admin_name') }}" placeholder="Enter full name" class="{{ $fieldClass }}" required>
                            @error('admin_name') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="admin_email" class="{{ $labelClass }}">Email Address</label>
                            <input id="admin_email" name="admin_email" type="email" value="{{ old('admin_email') }}" placeholder="Enter email address" class="{{ $fieldClass }}" required>
                            @error('admin_email') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="admin_mobile_no" class="{{ $labelClass }}">Phone Number</label>
                            <div class="mt-2 flex min-h-12 rounded-md border border-gray-200 bg-white shadow-sm focus-within:border-green-500 focus-within:ring-4 focus-within:ring-green-100">
                                <span class="flex items-center border-r border-gray-200 px-3 text-sm font-semibold text-gray-700">🇳🇬 +234</span>
                                <input id="admin_mobile_no" name="admin_mobile_no" type="tel" value="{{ old('admin_mobile_no') }}" placeholder="Enter phone number" class="min-w-0 flex-1 rounded-r-md px-3 py-3 text-sm outline-none">
                            </div>
                            @error('admin_mobile_no') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="password" class="{{ $labelClass }}">Password</label>
                            <div class="mt-2 flex min-h-12 rounded-md border border-gray-200 bg-white shadow-sm focus-within:border-green-500 focus-within:ring-4 focus-within:ring-green-100">
                                <input id="password" name="password" type="password" placeholder="Create password" class="min-w-0 flex-1 rounded-l-md px-3 py-3 text-sm outline-none" required minlength="8" data-password-input>
                                <button type="button" class="px-3 text-sm font-semibold text-green-700" data-password-toggle aria-controls="password">Show</button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Minimum 8 characters.</p>
                            @error('password') <p class="{{ $errorClass }}">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="{{ $labelClass }}">Confirm Password</label>
                            <div class="mt-2 flex min-h-12 rounded-md border border-gray-200 bg-white shadow-sm focus-within:border-green-500 focus-within:ring-4 focus-within:ring-green-100">
                                <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Confirm password" class="min-w-0 flex-1 rounded-l-md px-3 py-3 text-sm outline-none" required minlength="8" data-password-input>
                                <button type="button" class="px-3 text-sm font-semibold text-green-700" data-password-toggle aria-controls="password_confirmation">Show</button>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="fixed inset-x-0 bottom-0 border-t border-gray-200 bg-white/95 p-4 backdrop-blur lg:static lg:mt-8 lg:border-t-0 lg:bg-transparent lg:p-0">
                    <div class="flex gap-3 lg:justify-end">
                        <button type="button" class="hidden min-h-12 rounded-md border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 hover:bg-gray-50 lg:inline-flex lg:items-center" data-prev-step>Back</button>
                        <button type="button" class="min-h-12 flex-1 rounded-md bg-green-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-100 lg:flex-none" data-next-step>Next →</button>
                        <button type="submit" class="hidden min-h-12 flex-1 rounded-md bg-green-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-100 disabled:cursor-not-allowed disabled:opacity-70 lg:flex-none" data-submit-button>Create School</button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</main>
</body>
</html>
