<?php

namespace Ipstack\Wizard\Exception;

use Exception;
use Throwable;

class IncorrectFieldType extends Exception
{
    protected $code = 2;

    public function __construct($register, $name, $type, Throwable $previous = null)
    {
        $message = 'incorrect field type: '.$register.'.'.$name.' => '.$type;
        parent::__construct($message, $this->code, $previous);
    }
}