<?php

namespace App\Exceptions;

use RuntimeException;

class OpeningStockImportException extends RuntimeException
{
    public function __construct(private readonly array $errors, ?string $message = null)
    {
        parent::__construct($message ?? 'Opening stock import validation failed.');
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
