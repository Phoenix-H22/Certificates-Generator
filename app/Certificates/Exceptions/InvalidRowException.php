<?php

namespace App\Certificates\Exceptions;

use RuntimeException;

/** A spreadsheet row that cannot become a certificate. */
class InvalidRowException extends RuntimeException
{
    /** @param array<string, list<string>> $errors field => messages */
    public function __construct(
        public readonly int $rowNumber,
        public readonly array $errors,
    ) {
        parent::__construct(sprintf('Row %d: %s', $rowNumber, $this->summary()));
    }

    public function summary(): string
    {
        $parts = [];

        foreach ($this->errors as $field => $messages) {
            $parts[] = $field.': '.implode(', ', $messages);
        }

        return implode(' | ', $parts);
    }
}
