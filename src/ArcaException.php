<?php

namespace Arca;

/**
 * Error devuelto por ARCA o producido al comunicarse con sus web services.
 */
class ArcaException extends \Exception
{
    /** @var array Lista de errores [['code' => ..., 'msg' => ...], ...] devueltos por el servicio */
    private $errors;

    public function __construct(string $message, array $errors = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
