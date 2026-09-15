<?php

namespace App\Certificates\Exceptions;

use RuntimeException;
use Throwable;

/** Chromium could not produce a PDF/PNG for a certificate. */
class RenderFailedException extends RuntimeException
{
    public static function wrap(Throwable $previous, string $context = ''): self
    {
        $message = trim($context.' '.$previous->getMessage());

        return new self($message, (int) $previous->getCode(), $previous);
    }
}
