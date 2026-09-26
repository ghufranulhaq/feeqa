<?php

namespace App\Notifications;

use App\Models\EmployeeSizeBandDispute;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeSizeBandDisputeResolvedNotification extends Notification
{
    public function __construct(private readonly EmployeeSizeBandDispute $dispute) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->dispute->business;
        $changed = $this->dispute->status->value === 'changed';

        $message = (new MailMessage)
            ->subject("Your employee size band dispute for {$business->name} has been decided")
            ->line($changed
                ? "The employee size band for {$business->name} has been updated to {$business->employee_size_band->value}."
                : "The employee size band for {$business->name} stays as {$business->employee_size_band->value}.");

        if ($this->dispute->decision_notes) {
            $message->line("Notes: {$this->dispute->decision_notes}");
        }

        return $message;
    }
}
