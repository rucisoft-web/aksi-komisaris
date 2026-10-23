<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi dalam aplikasi (lonceng Filament) dan email opsional.
 * Isi email sengaja singkat dan tanpa isi rahasia; penerima membuka aplikasi untuk detail.
 */
class AksiNotification extends Notification
{
    use Queueable;

    public function __construct(public string $title, public string $body, public ?string $url = null) {}

    public function via(object $notifiable): array
    {
        return config('aksi.notify_mail') ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()->title($this->title)->body($this->body);

        if ($this->url) {
            $notification->actions([Action::make('open')->label('Buka')->url($this->url)->markAsRead()]);
        }

        return $notification->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[AKSI Komisaris] '.$this->title)
            ->line($this->title.'.')
            ->line('Silakan buka aplikasi untuk melihat detailnya.')
            ->action('Buka AKSI Komisaris', $this->url ?? url('/app'));
    }
}
