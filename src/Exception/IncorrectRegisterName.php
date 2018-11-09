<?php

namespace Ipstack\Wizard\Exception;

use Exception;
use Throwable;

class IncorrectRegisterName extends Exception
{
    protected $code = 1;

    public function __construct($name, Throwable $previous = null)
    {
        $message = 'incorrect register name: '.$name;
        parent::__construct($message, $this->code, $previous);
    }
}