<?php

namespace Ipstack\Wizard\Exception;

use Exception;
use Throwable;

class FieldNotFound extends Exception
{
    protected $code = 6;

    public function __construct($register, $field, Throwable $previous = null)
    {
        $message = 'field ' . $field . ' not found in ' . $register;
        parent::__construct($message, $this->code, $previous);
    }
}