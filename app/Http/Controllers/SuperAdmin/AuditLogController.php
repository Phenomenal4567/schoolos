<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $schoolId = $request->query('school_id');

        $logs = AuditLog::query()
            ->with(['actor.role', 'school'])
            ->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('super-admin.audit-logs.index', [
            'logs' => $logs,
            'schools' => School::query()->orderBy('name')->get(),
            'selectedSchoolId' => $schoolId,
        ]);
    }
}
