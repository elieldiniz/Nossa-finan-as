<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Aviso no sininho: "<conta> vence hoje". Um por conta e por dia. */
class DueBillToday extends Notification
{
    /** @param  array{type: string, id: int, month: ?string, name: string, due: string, amount: int}  $bill */
    public function __construct(public array $bill, public string $key) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => $this->key,
            'type' => $this->bill['type'],
            'id' => $this->bill['id'],
            'month' => $this->bill['month'],
            'name' => $this->bill['name'],
            'amount' => $this->bill['amount'],
            'due' => $this->bill['due'],
            'emailed' => false,
        ];
    }
}
