@extends('layouts.app')

@section('title', 'Notifications — SchoolOS')
@section('page-title', 'Notifications')

@section('content')
    @if ($notifications->isEmpty())
        <x-empty-state title="No notifications yet" description="You're all caught up." />
    @else
        <div class="space-y-2">
            @foreach ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $isUnread = $notification->read_at === null;
                @endphp
                <div class="flex items-start gap-3 rounded-lg border bg-white p-4 shadow-sm {{ $isUnread ? 'border-indigo-200' : 'border-gray-200' }}">
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $isUnread ? 'bg-indigo-500' : 'bg-gray-200' }}"
                        aria-hidden="true"
                    ></span>
                    <div class="min-w-0 flex-1">
                        @if (($data['type'] ?? null) === 'announcement.published')
                            <p class="text-sm font-medium text-gray-900">
                                New announcement: {{ $data['title'] ?? 'Untitled' }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ ($data['audience_type'] ?? null) === 'school' ? 'Whole school' : 'Your class' }}
                            </p>
                        @elseif (($data['type'] ?? null) === 'attendance.absent')
                            <p class="text-sm font-medium text-gray-900">Absence recorded</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ $data['date'] ?? '' }} &middot; {{ ucfirst($data['session'] ?? '') }} session
                            </p>
                        @elseif (($data['type'] ?? null) === 'fee.assessed')
                            <p class="text-sm font-medium text-gray-900">New fee assessed</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                &#8358;{{ $data['amount_due'] ?? '0.00' }} due
                                @if (! empty($data['due_date']))
                                    by {{ $data['due_date'] }}
                                @endif
                            </p>
                        @elseif (($data['type'] ?? null) === 'fee.payment_confirmed')
                            <p class="text-sm font-medium text-gray-900">Payment confirmed</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                &#8358;{{ $data['amount'] ?? '0.00' }} received
                            </p>
                        @elseif (($data['type'] ?? null) === 'fee.overdue')
                            <p class="text-sm font-medium text-gray-900">Fee payment overdue</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                &#8358;{{ $data['amount_remaining'] ?? '0.00' }} outstanding
                                @if (! empty($data['due_date']))
                                    since {{ $data['due_date'] }}
                                @endif
                            </p>
                        @elseif (($data['type'] ?? null) === 'fee.deadline_upcoming')
                            <p class="text-sm font-medium text-gray-900">Fee due soon</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                &#8358;{{ $data['amount_remaining'] ?? '0.00' }} due
                                @if (! empty($data['due_date']))
                                    by {{ $data['due_date'] }}
                                @endif
                            </p>
                        @else
                            <p class="text-sm font-medium text-gray-900">Notification</p>
                        @endif
                        <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at?->diffForHumans() }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif
@endsection
