<?php

namespace Ipstack\Wizard\Database;

use PDOStatement;

class Rows
{

    /**
     * @var PDOStatement
     */
    protected $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    public function next()
    {
        return $this->statement->fetch();
    }
}