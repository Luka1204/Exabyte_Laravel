<?php

namespace App\Modules\Subscriptions\Domain\Exceptions;

use RuntimeException;

class MercadoPagoNotConfigured extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Mercado Pago is not configured.');
    }
}
