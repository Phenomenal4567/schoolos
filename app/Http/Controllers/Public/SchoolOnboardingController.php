<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\School;
use App\Models\Standard;
use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Public self-service school onboarding.
 *
 * This is intentionally a guest route and not a second auth system: it
 * creates the school_admin User row inside the same users/roles/schools
 * model used everywhere else, logs that user in through AuthenticationService,
 * and lands them in Admin\SetupWizardController for the granular setup work.
 */
class SchoolOnboardingController extends Controller
{
    private const EDUCATION_LEVELS = [
        'creche_nursery' => 'Creche / Nursery',
        'primary' => 'Primary',
        'junior_secondary' => 'Junior Secondary',
        'senior_secondary' => 'Senior Secondary',
        'college_tertiary' => 'College / Tertiary',
    ];

    private const SCHOOL_TYPES = [
        'creche' => 'Creche',
        'primary' => 'Primary',
        'secondary' => 'Secondary',
        'college_tertiary' => 'College / Tertiary',
        'mixed' => 'Mixed',
    ];

    private const GRADING_SYSTEMS = [
        'percentage' => 'Percentage',
        'letter' => 'Letter grades',
        'remarks' => 'Remarks only',
    ];

    private const WORKING_DAYS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
    ];

    private const STANDARD_TEMPLATES = [
        'creche_nursery' => ['Creche', 'Nursery 1', 'Nursery 2', 'Kindergarten'],
        'primary' => ['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6'],
        'junior_secondary' => ['JSS 1', 'JSS 2', 'JSS 3'],
        'senior_secondary' => ['SSS 1', 'SSS 2', 'SSS 3'],
        'college_tertiary' => ['Year 1', 'Year 2', 'Year 3', 'Year 4'],
    ];

    public function create(): View
    {
        return view('public.onboarding.create', [
            'educationLevels' => self::EDUCATION_LEVELS,
            'schoolTypes' => self::SCHOOL_TYPES,
            'gradingSystems' => self::GRADING_SYSTEMS,
            'workingDays' => self::WORKING_DAYS,
            'moduleKeys' => School::MODULE_KEYS,
            'trialDays' => PlatformSetting::current()->trial_days,
        ]);
    }

    public function store(Request $request, AuthenticationService $authService): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:255', 'unique:schools,name'],
            'school_email' => ['required', 'email', 'max:255', 'unique:schools,email'],
            'school_phone' => ['required', 'string', 'max:255', 'unique:schools,phone'],
            'school_type' => ['required', Rule::in(array_keys(self::SCHOOL_TYPES))],
            'address' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'education_levels' => ['required', 'array', 'min:1'],
            'education_levels.*' => [Rule::in(array_keys(self::EDUCATION_LEVELS))],
            'academic_year_label' => ['required', 'string', 'max:50'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => [Rule::in(array_keys(self::WORKING_DAYS))],
            'grading_system' => ['required', Rule::in(array_keys(self::GRADING_SYSTEMS))],
            'allow_manual_promotion' => ['nullable', 'boolean'],
            'enabled_modules' => ['required', 'array', 'min:1'],
            'enabled_modules.*' => [Rule::in(School::MODULE_KEYS)],
            'billing_plan' => ['required', Rule::in(['trial', 'paid'])],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_mobile_no' => ['nullable', 'string', 'max:255', 'unique:users,mobile_no'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = DB::transaction(function () use ($data): User {
            $school = School::create([
                'name' => $data['school_name'],
                'email' => $data['school_email'],
                'phone' => $data['school_phone'],
                'school_type' => $data['school_type'],
                'location' => $this->locationFrom($data),
                'working_days' => array_values($data['working_days']),
                'education_levels' => array_values($data['education_levels']),
                'grading_system' => $data['grading_system'],
                'allow_manual_promotion' => (bool) ($data['allow_manual_promotion'] ?? false),
                'enabled_modules' => array_values($data['enabled_modules']),
                'billing_plan' => $data['billing_plan'],
                'trial_ends_at' => $data['billing_plan'] === 'trial'
                    ? now()->addDays(PlatformSetting::current()->trial_days)
                    : null,
            ]);

            $school->short_code = $this->shortCodeFor($school);
            $school->save();

            AcademicYear::create([
                'school_id' => $school->id,
                'label' => $data['academic_year_label'],
                'is_current' => true,
            ]);

            foreach ($this->starterStandards($data['education_levels']) as $standardName) {
                Standard::create([
                    'school_id' => $school->id,
                    'name' => $standardName,
                ]);
            }

            $adminRole = Role::where('key', 'school_admin')->firstOrFail();

            return User::create([
                'school_id' => $school->id,
                'role_id' => $adminRole->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'mobile_no' => $data['admin_mobile_no'] ?? null,
                'password' => $data['password'],
                'status' => 'active',
            ]);
        });

        $authService->issueSession($admin);

        return redirect()->route('admin.setup.index')
            ->with('status', 'Welcome to SchoolOS. Your school workspace is ready for setup.');
    }

    private function locationFrom(array $data): string
    {
        return collect([$data['address'], $data['city'], $data['state'], $data['country']])
            ->filter()
            ->implode(', ');
    }

    /**
     * @param list<string> $levels
     *
     * @return list<string>
     */
    private function starterStandards(array $levels): array
    {
        return collect($levels)
            ->flatMap(fn (string $level) => self::STANDARD_TEMPLATES[$level] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    private function shortCodeFor(School $school): string
    {
        $base = collect(preg_split('/\s+/', trim($school->name)))
            ->filter()
            ->map(fn (string $word) => Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $word), 0, 1)))
            ->implode('');

        $base = $base !== '' ? $base : 'SCH';
        $candidate = $base;
        $suffix = 1;

        while (School::where('short_code', $candidate)->whereKeyNot($school->id)->exists()) {
            $suffix++;
            $candidate = $base . $suffix;
        }

        return $candidate;
    }
}
