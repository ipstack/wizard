<?php

namespace Ipstack\Wizard\Exception;

use Exception;
use Throwable;

class RegisterNotFound extends Exception
{
    protected $code = 4;

    public function __construct($register, Throwable $previous = null)
    {
        $message = 'register not found: '.$register;
        parent::__construct($message, $this->code, $previous);
    }
}