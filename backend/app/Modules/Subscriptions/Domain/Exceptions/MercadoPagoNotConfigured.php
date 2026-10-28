<?php

namespace App\Modules\Subscriptions\Domain\Exceptions;

use RuntimeException;

class MercadoPagoNotConfigured extends RuntimeException
{
    public function __construct(string $message = 'Mercado Pago no está configurado correctamente.')
    {
        parent::__construct($message);
    }
}
