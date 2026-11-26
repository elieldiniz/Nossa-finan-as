<?php

namespace App\Notifications;

use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail do dia com as contas que vencem hoje. */
class DueBillsTodayMail extends Notification
{
    /** @param  array<int, array{name: string, amount: int}>  $bills */
    public function __construct(public array $bills) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->bills);
        $mail = (new MailMessage)
            ->subject($count === 1 ? 'Vence hoje: '.$this->bills[0]['name'] : "Vencem hoje: {$count} contas")
            ->greeting('Olá, '.$notifiable->firstName().'!')
            ->line($count === 1 ? 'Esta conta vence hoje:' : 'Estas contas vencem hoje:');

        foreach ($this->bills as $bill) {
            $mail->line('• '.$bill['name'].' — '.Money::format($bill['amount']));
        }

        return $mail->action('Abrir o Nossa Finanças', url('/dashboard'))
            ->line('Depois de pagar, toque em “Pago” em Contas a vencer.')
            ->salutation('Nossa Finanças');
    }
}
