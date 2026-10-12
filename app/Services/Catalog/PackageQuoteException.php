<?php

namespace App\Services\Catalog;

use InvalidArgumentException;

class PackageQuoteException extends InvalidArgumentException
{
    public function __construct(string $message, public readonly string $field = 'units')
    {
        parent::__construct($message);
    }
}
