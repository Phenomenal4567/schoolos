<?php

namespace App\Providers;

use App\Contracts\PaystackClient;
use App\Events\AnnouncementPublished;
use App\Events\AttendanceCorrected;
use App\Events\AttendanceMarked;
use App\Events\FeeAssessed;
use App\Events\PaymentConfirmed;
use App\Events\TimetableSlotChanged;
use App\Listeners\Academic\NotifyTeacherOfTimetableChange;
use App\Listeners\Attendance\NotifyParentsOfAbsence;
use App\Listeners\Communication\NotifyAudienceOfAnnouncement;
use App\Listeners\Finance\NotifyParentsOfFeeAssessment;
use App\Listeners\Finance\NotifyParentsOfPaymentConfirmation;
use App\Services\PaystackHttpClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Design ref: 21-schoolos-finance-architecture.md §3, D13 —
     * PaystackClient binds to the real HTTP implementation by default;
     * tests bind PaymentRepositoryTest's fake instead via
     * $this->app->instance(PaystackClient::class, ...).
     */
    public function register(): void
    {
        $this->app->bind(PaystackClient::class, PaystackHttpClient::class);
    }

    /**
     * Bootstrap any application services.
     *
     * Design ref: 14-schoolos-implementation-plan.md §5,
     * 18-schoolos-communication-domain-map.md §2/§8
     *
     * Explicit Event::listen() calls rather than Laravel's convention-based
     * auto-discovery, matching NotifyAudienceOfAnnouncement/
     * NotifyParentsOfAbsence's own doc comments, which already asserted
     * this registration existed here before it actually did (18 §2's
     * central finding — the highest-priority open item that finding left
     * behind). Wiring lives in exactly one place so "is this listener
     * actually firing" is answered by reading this method, not by
     * grepping for a directory-convention match.
     *
     * NotifyParentsOfAbsence is registered against AttendanceMarked only
     * — see that listener's own doc comment on why AttendanceCorrected is
     * a separate, deliberate call (D8 below), not folded into this one
     * registration.
     */
    public function boot(): void
    {
        Event::listen(AnnouncementPublished::class, NotifyAudienceOfAnnouncement::class);
        Event::listen(AttendanceMarked::class, NotifyParentsOfAbsence::class);

        // D8 (16-schoolos-decisions-register.md): a correction that
        // changes status *to* 'absent' also notifies parents, via the
        // same notification NotifyParentsOfAbsence sends — see that
        // listener's handleCorrection() doc comment for why this is a
        // second method on the existing listener rather than a new class.
        Event::listen(AttendanceCorrected::class, [NotifyParentsOfAbsence::class, 'handleCorrection']);

        // Fee / Debt Notifications (26-discovery-hierarchy-status.md).
        Event::listen(FeeAssessed::class, NotifyParentsOfFeeAssessment::class);
        Event::listen(PaymentConfirmed::class, NotifyParentsOfPaymentConfirmation::class);

        // Timetable Management module: notifies the affected teacher
        // whenever an admin (or delegated staff member) changes one of
        // their slots — see TimetableSlotChanged's own doc comment.
        Event::listen(TimetableSlotChanged::class, NotifyTeacherOfTimetableChange::class);
    }
}
