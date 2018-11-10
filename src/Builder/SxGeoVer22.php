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
        $fh = fopen($file, 'wb');
        $header =
            self::CONTROL
            . pack('C', self::VERSION) // version
            . pack('N', $this->database->getTime()) // time
            . pack('C', 2) // type 2 - sxgeo city
            . pack('C', 0) // charset 0 - UTF
            . pack('C', 224) // b index len
            . pack('n', null) // m idx len
            . pack('n', null) // range
            . pack('N', null) // db items
            . pack('C', 3) // id len
            . pack('n', null) // max region
            . pack('n', null) // max city
            . pack('N', null) // region size
            . pack('N', null) // city size
            . pack('n', null) // max country
            . pack('N', null) // country size
            . pack('n', null) // pack size
        ;
        fwrite($fh, $header);
        fclose($fh);
    }
}