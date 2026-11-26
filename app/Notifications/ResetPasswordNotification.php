<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notificação de redefinição de senha em português.
 */
class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $broker = config('auth.defaults.passwords');
        $expire = (int) config("auth.passwords.{$broker}.expire", 60);

        return (new MailMessage)
            ->subject('Redefinição de senha — '.config('app.name'))
            ->greeting('Olá!')
            ->line('Recebemos um pedido de redefinição de senha para a sua conta.')
            ->action('Redefinir senha', $this->resetUrl($notifiable))
            ->line("Este link expira em {$expire} minutos.")
            ->line('Se você não solicitou a redefinição, nenhuma ação é necessária.')
            ->salutation('Atenciosamente, '.config('app.name'));
    }
}
