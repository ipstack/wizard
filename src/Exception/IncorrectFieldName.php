<?php

namespace Ipstack\Wizard\Exception;

use Exception;
use Throwable;

class IncorrectFieldName extends Exception
{
    protected $code = 2;

    public function __construct($register, $name, Throwable $previous = null)
    {
        $message = 'incorrect field name: '.$register.'.'.$name;
        parent::__construct($message, $this->code, $previous);
    }
}