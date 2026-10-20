<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    // Com pagamento online: o hóspede tem até payment_due_at para pagar; pago, vira Confirmed sozinho.
    case AwaitingPayment = 'awaiting_payment';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    // Passou o prazo sem pagamento: as datas voltam a ficar livres.
    case Expired = 'expired';

    /**
     * Status que ocupam o calendário. Um pedido pendente segura as datas
     * até o dono confirmar ou recusar, para evitar dois pedidos no mesmo dia.
     * Quem está pagando também segura, mas só até o prazo (ver Booking::scopeHoldingDates).
     *
     * @return list<self>
     */
    public static function holdingDates(): array
    {
        return [self::Pending, self::AwaitingPayment, self::Confirmed];
    }
}
