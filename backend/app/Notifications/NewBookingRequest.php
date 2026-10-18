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
            ->line('**'.$this->plain($booking->guest_name)."** quer reservar **{$accommodations}**.")
            ->line('Entrada: '.$booking->check_in->format('d/m/Y').' · Saída: '.$booking->check_out->format('d/m/Y')." ({$booking->nights()} diária(s))")
            ->line("Hóspedes: {$booking->guests} · Total: R$ ".number_format($booking->total / 100, 2, ',', '.'))
            ->line('WhatsApp do hóspede: '.$this->plain($booking->guest_phone))
            ->when($booking->notes, fn (MailMessage $mail) => $mail->line('Observações: '.$this->plain($booking->notes)))
            ->action('Abrir o painel', rtrim((string) config('app.frontend_url'), '/').'/painel')
            ->line('As datas ficam seguras até você confirmar ou recusar o pedido.');
    }

    /**
     * Texto digitado pelo hóspede, sem virar Markdown. O e-mail é montado em Markdown, então
     * "[clique aqui](https://...)" no nome viraria um link de verdade dentro do aviso ao dono.
     * O HTML já é escapado pelo Blade e cada linha vira um parágrafo de uma linha só, então
     * basta escapar o que forma marcação no meio do texto: links, ênfase e código.
     */
    private function plain(string $text): string
    {
        return addcslashes($text, '\\`*_[]');
    }
}
