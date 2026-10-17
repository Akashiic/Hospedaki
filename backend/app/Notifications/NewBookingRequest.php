<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa o dono da pousada que chegou um pedido de reserva novo.
 */
class NewBookingRequest extends Notification
{
    use Queueable;

    public function __construct(public readonly Booking $booking) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing('accommodations');
        $accommodations = $booking->accommodations->pluck('name')->join(', ', ' e ');

        return (new MailMessage)
            ->subject("Novo pedido de reserva: {$booking->guest_name}")
            ->greeting('Chegou um pedido de reserva!')
            ->line("**{$booking->guest_name}** quer reservar **{$accommodations}**.")
            ->line('Entrada: '.$booking->check_in->format('d/m/Y').' · Saída: '.$booking->check_out->format('d/m/Y')." ({$booking->nights()} diária(s))")
            ->line("Hóspedes: {$booking->guests} · Total: R$ ".number_format($booking->total / 100, 2, ',', '.'))
            ->line("WhatsApp do hóspede: {$booking->guest_phone}")
            ->when($booking->notes, fn (MailMessage $mail) => $mail->line("Observações: {$booking->notes}"))
            ->action('Abrir o painel', rtrim((string) config('app.frontend_url'), '/').'/painel')
            ->line('As datas ficam seguras até você confirmar ou recusar o pedido.');
    }
}
