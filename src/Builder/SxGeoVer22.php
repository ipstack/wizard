<?php

namespace Ipstack\Wizard\Builder;

use Ipstack\Wizard\Database\Database;

class SxGeoVer22 implements BuilderInterface
{

    const VERSION = 22;

    const CONTROL = 'SxG';

    /**
     * @var Database
     */
    protected $database;

    /**
     * @param Database $database
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * @param string $file
     * @param array $options
     * @return void
     */
    public function build($file, $options=array())
    {

        // TODO: Implement build() method.
        $header = self::CONTROL
            . pack('CICC', self::VERSION, $this->database->getTime(), 2, 0)
        ;

    }
}