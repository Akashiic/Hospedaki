<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    /**
     * Status que ocupam o calendário. Um pedido pendente segura as datas
     * até o dono confirmar ou recusar, para evitar dois pedidos no mesmo dia.
     *
     * @return list<self>
     */
    public static function holdingDates(): array
    {
        return [self::Pending, self::Confirmed];
    }
}
