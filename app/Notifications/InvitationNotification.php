<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
 * Invitation mechanism"
 *
 * The first mail-channel notification in this codebase — every other
 * Notifications\* class here is database-channel only (an in-app bell
 * icon), which only reaches someone who already has a session; an
 * invitee by definition doesn't yet. MAIL_MAILER=log in this
 * environment's .env means this still just writes to the log rather
 * than delivering anywhere real, matching how every other outbound
 * integration in this app (Paystack, notifications) behaves under its
 * current environment config rather than assuming production
 * credentials are present.
 *
 * $plainToken is carried on the notification instance, never persisted
 * anywhere (Invitation::token_hash is the only stored form — see that
 * model's doc comment) — this class exists only long enough to render
 * one email.
 */
class InvitationNotification extends Notification
{
    public function __construct(
        private readonly Invitation $invitation,
        private readonly string $plainToken,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->invitation->school;
        $roleLabel = $this->invitation->role->label ?? 'SchoolOS';
        $acceptUrl = route('public.invitations.create', ['token' => $this->plainToken]);

        return (new MailMessage())
            ->subject("You're invited to {$school->name}")
            ->greeting("You're invited to {$school->name}")
            ->line("You've been added to {$school->name} on SchoolOS as {$roleLabel}.")
            ->action('Set your password', $acceptUrl)
            ->line('This invitation link expires in a few days and can only be used once.');
    }
}
