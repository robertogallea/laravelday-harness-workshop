<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

class RefundIssued extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public Refund $refund,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = Number::currency((float) $this->refund->amount, $this->order->currency, 'it');

        return (new MailMessage)
            ->subject("Rimborso dell'ordine #{$this->order->id}")
            ->line("Abbiamo rimborsato {$amount} sul metodo di pagamento usato per l'ordine #{$this->order->id}.")
            ->line("L'accredito può richiedere alcuni giorni lavorativi.");
    }
}
