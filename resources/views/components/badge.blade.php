@props(['status' => null, 'variant' => null])

@php
    // Attendance statuses come straight from the backend's Rule::in(['present','absent','late','excused'])
    // (TeacherPortal\AttendanceController::store()) — this map is presentation only,
    // it does not define which statuses are valid.
    $statusColors = [
        'present' => 'bg-green-50 text-green-700 ring-green-600/20',
        'absent' => 'bg-red-50 text-red-700 ring-red-600/10',
        'late' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'excused' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    ];

    $variantColors = [
        'success' => 'bg-green-50 text-green-700 ring-green-600/20',
        'danger' => 'bg-red-50 text-red-700 ring-red-600/10',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'neutral' => 'bg-gray-100 text-gray-600 ring-gray-500/10',
    ];

    $classes = $statusColors[$status] ?? $variantColors[$variant] ?? $variantColors['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset $classes"]) }}>
    {{ $slot }}
</span>
