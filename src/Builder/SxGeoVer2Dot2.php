<?php

namespace Ipstack\Wizard\Builder;

use Ipstack\Wizard\Database\Database;
use Ipstack\Wizard\Exception\RegisterNotFound;

class SxGeoVer2Dot2 implements BuilderInterface
{

    const VERSION = 22;

    const CONTROL = 'SxG';

    /**
     * @var Database
     */
    protected $database;

    /**
     * @var array
     */
    protected $meta = array(
        'registers' => array(),
        'networks' => array(),
        'index' => array(),
    );

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
     * @throws RegisterNotFound
     */
    public function build($file, $options=array())
    {
        $files['register'] = $this->createRegisters();
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

    /**
     * @return array
     * @throws RegisterNotFound
     */
    protected function createRegisters()
    {
        $files = array();
        foreach ($this->database->getRegisters() as $register => $fields) {
            $limits = $this->database->getRegisterLimits($register);
            //$offset = 0;
            $format = array();
            $empty = array();
            foreach ($fields as $field=>$type) {
                $empty[$field] = null;
                $f = $this->optimalPackFormat($type, $limits[$field]['min'], $limits[$field]['max']);
                $format['pack'][] = $f;
                $format['unpack'][] = $f.$field;
            }
            //$pack = implode('', $format['pack']);
            //$bin = self::packArray($pack, $empty);
            $this->meta['registers'][$register]['format'] = implode(':',$format['unpack']);
            //$this->meta['registers'][$register]['len'] = strlen($bin);
            /*
            $file = $this->generateTemporaryFileName('register.'.$register);
            $fh = fopen($file, 'w');
            fwrite($fh, $bin);
            $rows = $this->database->getRegisterRows($register);
            while ($row = $rows->next()) {
                $data = $empty;
                $check = 0;
                foreach (array_keys($data) as $field) {
                    $value = empty($row[$field])?null:$row[$field];
                    if (!empty($value)) $check = 1;
                    $data[$field] = $value;
                }
                $bin = self::packArray($pack, $data);
                if ($check) {
                    $offset ++;
                    fwrite($fh, $bin);
                }
                $this->database->setOffset($register, $row['_key'], $offset);
            }
            $this->meta['registers'][$register]['items'] = $offset;
            fclose($fh);
            $files[$register] = $file;
            */
        }
        print_r($this->meta);
        return $files;
    }

    /**
     * @param string $format
     * @param array $data
     * @return string
     */
    protected function packArray($format, $data)
    {
        $packParams = array_values($data);
        array_unshift($packParams, $format);
        return call_user_func_array('pack', $packParams);
    }

    protected function optimalPackFormat($type, $min, $max)
    {
        if ($min > $max) {
            $max = $max + $min;
            $min = $max - $min;
            $max = $max - $min;
        }
        $format = 'c'.$max;
        switch ($type) {
            case Database::TYPE_INT:
                $format = 'I';
                if ($min >= -2147483648 && $max <= 2147483647) $format = 'i';
                if ($min >= 0 && $max <= 16777215) $format = 'M';
                if ($min >= -8388608 && $max <= 8388607) $format = 'm';
                if ($min >= 0 && $max <= 65535) $format = 'S';
                if ($min >= -32768 && $max <= 32767) $format = 's';
                if ($min >= 0 && $max <= 255) $format = 'T';
                if ($min >=-128 && $max <= 127) $format = 't';
                break;
            case Database::TYPE_FLOAT:
                $format = 'f';
                break;
            case Database::TYPE_DOUBLE: // no break
            case Database::TYPE_NUMBER:
                $format = 'd';
                break;
            case Database::TYPE_STRING:
                $format = 'b';
                break;
        }
        return $format;
    }
}