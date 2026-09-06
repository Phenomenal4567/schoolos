<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: SchoolOS Onboarding & Authentication UI — demo/trial
 * billing addition ("super admin should be able to set days for demo
 * account creation").
 *
 * One action, no route parameter (mutates the single PlatformSetting
 * row via PlatformSetting::current(), never resolved by id) — exempt
 * from the route-table's scope-checked lint the same way every other
 * id-less write action in this bundle is.
 */
class PlatformSettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $setting = PlatformSetting::current();
        $setting->trial_days = $data['trial_days'];
        $setting->save();

        return back()->with('status', 'Trial length updated.');
    }
}
