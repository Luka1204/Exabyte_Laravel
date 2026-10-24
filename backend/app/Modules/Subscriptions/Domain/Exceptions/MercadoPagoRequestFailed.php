<?php

namespace App\Modules\Subscriptions\Domain\Exceptions;

use RuntimeException;
use Throwable;

class MercadoPagoRequestFailed extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Mercado Pago request failed.', 0, $previous);
    }
}
