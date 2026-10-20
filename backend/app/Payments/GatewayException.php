<?php

namespace App\Payments;

use RuntimeException;

/**
 * Falha ao falar com o gateway. A mensagem é para o hóspede ou para o dono ler.
 */
class GatewayException extends RuntimeException {}
