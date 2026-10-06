<?php

namespace App\Services;

class ZohoCrmException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $zohoCode = null,
    ) {
        parent::__construct($message);
    }
}
