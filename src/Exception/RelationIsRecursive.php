<?php

namespace Ipstack\Wizard\Exception;

use Exception;
use Throwable;

class RelationIsRecursive extends Exception
{
    protected $code = 7;

    public function __construct($parent, $field, $child, Throwable $previous = null)
    {
        $message = 'relation ' . $parent . '.' . $field . ' => ' . $child . ' is a recursive';
        parent::__construct($message, $this->code, $previous);
    }
}