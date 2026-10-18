<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

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
        $nights = $booking->nights();
        $unpriced = (int) $booking->extra_guests_unpriced;
        $guests = $unpriced > 0 ? "{$booking->guests} ({$unpriced} a mais; acréscimo a combinar)" : (string) $booking->guests;

        return (new MailMessage)
            ->subject("Novo pedido de reserva: {$booking->guest_name}")
            ->greeting('Chegou um pedido de reserva!')
            ->line(new HtmlString('**'.$this->plain($booking->guest_name).'** quer reservar **'.$this->plain($accommodations).'**.'))
            ->line('Entrada: '.$booking->check_in->format('d/m/Y').' · Saída: '.$booking->check_out->format('d/m/Y')." ({$nights} ".($nights === 1 ? 'diária' : 'diárias').')')
            ->line("Hóspedes: {$guests} · Total: R$ ".number_format($booking->total / 100, 2, ',', '.'))
            ->when($unpriced > 0, fn (MailMessage $mail) => $mail->line('O total não inclui o acréscimo das pessoas a mais: combine o valor com o hóspede.'))
            ->line(new HtmlString('WhatsApp do hóspede: '.$this->plain($booking->guest_phone)))
            ->when($booking->notes, fn (MailMessage $mail) => $mail->line(new HtmlString('Observações: '.$this->plain($booking->notes))))
            ->action('Abrir o painel', rtrim((string) config('app.frontend_url'), '/').'/painel')
            ->line('As datas ficam seguras até você confirmar ou recusar o pedido.');
    }

    /**
     * Texto digitado pelo hóspede, sem virar Markdown. O e-mail é montado em Markdown, então
     * "[clique aqui](https://...)" no nome viraria um link de verdade dentro do aviso ao dono.
     *
     * Escapa o HTML e troca os caracteres que formam marcação (links, ênfase, código) por
     * entidades numéricas. A linha vai como HtmlString para o Blade não escapar de novo. No HTML,
     * o CommonMark mostra a entidade como o caractere literal, sem formar marcação; na versão em
     * texto, o Laravel decodifica as entidades. Assim nenhuma das duas mostra barras invertidas.
     * HtmlString pula a junção de linhas do MailMessage, então as quebras viram espaço aqui:
     * uma linha nova começando com "#" ou "-" viraria título ou lista.
     */
    private function plain(string $text): string
    {
        $text = trim(implode(' ', array_map('trim', preg_split('/\r\n|\r|\n/', $text))));

        return strtr(e($text), ['\\' => '&#92;', '`' => '&#96;', '*' => '&#42;', '_' => '&#95;', '[' => '&#91;', ']' => '&#93;']);
    }
}
