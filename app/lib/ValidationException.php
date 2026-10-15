<?php
declare(strict_types=1);

/** Fachliche Fehler (z. B. Offerte bereits angenommen). Meldung wird angezeigt. */
class ValidationException extends RuntimeException
{
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
