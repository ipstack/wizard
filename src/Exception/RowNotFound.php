<?php

namespace Ipstack\Wizard\Exception;

use Exception;
use Throwable;

class RowNotFound extends Exception
{
    protected $code = 5;

    public function __construct($register, $key, Throwable $previous = null)
    {
        $message = 'row '.$key.' not found in '.$register;
        parent::__construct($message, $this->code, $previous);
    }
}