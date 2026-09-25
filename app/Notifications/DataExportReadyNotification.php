<?php

namespace App\Notifications;

use App\Models\DataExport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class DataExportReadyNotification extends Notification
{
    public function __construct(private readonly DataExport $export) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'data-exports.download',
            $this->export->expires_at,
            ['dataExport' => $this->export->id],
        );

        return (new MailMessage)
            ->subject('Your data export is ready')
            ->line('The data export you requested is ready to download.')
            ->action('Download my data', $url)
            ->line('This link expires in 7 days.');
    }
}
