<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $schools = School::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $roleCounts = User::query()
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->selectRaw('roles.key, count(*) as total')
            ->groupBy('roles.key')
            ->orderBy('roles.key')
            ->pluck('total', 'key');

        $recentAuditLogs = AuditLog::query()
            ->with(['actor', 'school'])
            ->latest()
            ->limit(10)
            ->get();

        return view('super-admin.dashboard', [
            'schools' => $schools,
            'roleCounts' => $roleCounts,
            'recentAuditLogs' => $recentAuditLogs,
            'activeSchoolsCount' => $schools->where('status', 'active')->count(),
            'suspendedSchoolsCount' => $schools->where('status', 'suspended')->count(),
            'platformSetting' => PlatformSetting::current(),
        ]);
    }
}
