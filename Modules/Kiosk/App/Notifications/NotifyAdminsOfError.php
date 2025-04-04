<?php

namespace Modules\Kiosk\App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Modules\Kiosk\App\Models\KioskEvent;

class NotifyAdminsOfError extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public KioskEvent $event)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['slack'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', 'https://laravel.com')
            ->line('Thank you for using our application!');
    }

    public function toSlack($notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->text($this->event->data['message'])
            ->headerBlock('Device Error')
            ->contextBlock(function (ContextBlock $contextBlock) {
                $contextBlock->text('Date Time: '.$this->event->date_time);
                $contextBlock->text('Device ID: '.$this->event->device_name);
            });
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
