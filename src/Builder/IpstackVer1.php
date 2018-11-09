<?php

namespace Ipstack\Wizard\Builder;

use Ipstack\Wizard\Database\Database;
use Ipstack\Wizard\Exception\RegisterNotFound;

class IpstackVer1 implements BuilderInterface
{

    const VERSION = 1;

    const CONTROL = 'ISD';

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
     * @var array
     */
    protected $root = array();

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
        $files['network'] = $this->createNetwork();
        /* Create header */
        $header = pack('C', self::VERSION);
        $header .= pack('C', count($this->meta['registers']));
        $nameLen = 1;
        $packLen = strlen($this->meta['networks']['pack']);
        $len = ($this->meta['networks']['len'] > 255)?'I1':'C1';
        $itm = ($this->meta['networks']['items'] > 255)?'I1':'C1';
        foreach ($this->meta['registers'] as $registerName => $register) {
            if (strlen($registerName) > $nameLen) $nameLen = strlen($registerName);
            if (strlen($register['pack']) > $packLen) $packLen = strlen($register['pack']);
            if (($register['len'] > 255) && $len == 'C1') $len = 'I1';
            if (($register['items'] > 255) && $itm == 'C1') $itm = 'I1';
        }
        $pack = 'A'.$nameLen.'A'.$packLen.$len.$itm;
        $unpack = 'A'.$nameLen.'name/A'.$packLen.'pack/'.$len.'len/'.$itm.'items';
        $header .= pack('I',strlen($unpack));
        $header .= pack('A*',$unpack);
        $header .= pack('I',strlen(pack($pack,'','',0,0)));
        foreach ($this->meta['registers'] as $registerName => $register) {
            $header .= pack($pack,$registerName,$register['pack'],$register['len'],$register['items']);
        }
        $header .= pack(
            $pack,
            'n',
            $this->meta['networks']['pack'],
            $this->meta['networks']['len'],
            $this->meta['networks']['items']
        );
        $header .= $this->packArray('I*', $this->meta['index']);
        $headerLen = strlen($header);
        $letter = 'C';
        if ($headerLen > 255) $letter = 'I';

        /* Create binary database */
        $database = fopen($file,'w');
        fwrite($database, self::CONTROL . $letter . pack($letter, $headerLen) . $header);

        /* Write networks to database */
        $stream = fopen($files['network'], 'rb');
        stream_copy_to_stream($stream, $database);
        fclose($stream);

        /* Remove networks temporary file */
        if (is_writable($files['network'])) unlink($files['network']);

        /* Write registers to database */
        foreach ($files['register'] as $register) {
            $stream = fopen($register, 'rb');
            stream_copy_to_stream($stream, $database);
            fclose($stream);
            /* Remove register temporary file */
            if (is_writable($register)) unlink($register);
        }
        $time = $this->database->getTime()?$this->database->getTime():time();
        fwrite($database,pack('N1A128',$time,$this->database->getAuthor()));
        fwrite($database,pack('A*',$this->database->getLicense()));
        fclose($database);
        return;
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
            $offset = 0;
            $format = array();
            $empty = array();
            foreach ($fields as $field=>$type) {
                $empty[$field] = null;
                $f = $this->optimalPackFormat($type, $limits[$field]['min'], $limits[$field]['max']);
                $format['pack'][] = $f;
                $format['unpack'][] = $f.$field;
            }
            $pack = implode('', $format['pack']);
            $bin = self::packArray($pack, $empty);
            $this->meta['registers'][$register]['pack'] = implode('/',$format['unpack']);
            $this->meta['registers'][$register]['len'] = strlen($bin);
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
        }
        return $files;
    }

    /**
     * @return string
     * @throws RegisterNotFound
     */
    protected function createNetwork()
    {
        $file = $this->generateTemporaryFileName('network');
        $this->root = array(
            'format' => array(),
            'fields' => array(),
            'values' => array(),
        );
        $ip = -1;
        $relations = $this->database->getRelations();
        $root = $this->database->getRootRegisters();
        foreach ($root as $register) {
            $this->addRegisterToRoot($register, $relations);
        }
        $pack = implode('', $this->root['format']['pack']);
        $binaryPrevData = self::packArray($pack, $this->root['fields']);
        $empty = pack('N', 0) . $binaryPrevData;
        $this->meta['networks']['pack'] = implode('/',$this->root['format']['unpack']);
        $offset = 0;
        $this->meta['networks']['len'] = strlen($empty);
        $this->meta['index'][0] = 0;
        $fh = fopen($file, 'w');
        $rows = $this->database->getNetworkRows();
        while ($row = $rows->next()) {
            if ($row['ip'] !== $ip) {
                foreach ($this->root['values'] as $param=>$v) {
                    $this->addFieldToRoot($param, $v, $relations);
                }
                $binaryData = self::packArray($pack, $this->root['fields']);
                if ($binaryData !== $binaryPrevData || empty($ip)) {
                    fwrite($fh, pack('N', $ip) . $binaryData);
                    $octet = (int)long2ip($ip);
                    if (!isset($this->meta['index'][$octet])) $this->meta['index'][$octet] = $offset;
                    $offset++;
                    $binaryPrevData = $binaryData;
                }
                $ip = $row['ip'];
            }
            if ($row['register']) {
                if ($row['action'] == Database::ACTION_REMOVE) {
                    $this->removeValueFromRoot($row['register'], $row['key'], $relations);
                } else {
                    $this->addValueToRoot($row['register'], $row['key'], $relations);
                }
            }
        }
        if ($ip < ip2long('255.255.255.255')) {
            foreach ($this->root['values'] as $param => $v) {
                if (!empty($param)) $this->root['fields'][$param] = array_pop($v);
            }
            $binaryData = self::packArray($pack, $this->root['fields']);
            if ($binaryData !== $binaryPrevData) {
                $octet = (int)long2ip($ip);
                if (!isset($this->meta['index'][$octet])) $this->meta['index'][$octet] = $offset;
                $offset++;
                fwrite($fh, pack('N', $ip) . $binaryData);
            }
        }
        $this->meta['networks']['items'] = $offset;
        for($i=1;$i<=255;$i++) {
            if (!isset($this->meta['index'][$i])) $this->meta['index'][$i] = $this->meta['index'][$i-1];
        }
        ksort($this->meta['index']);
        fclose($fh);
        unset($ip);
        return $file;
    }

    /**
     * @param string $suffix
     * @return string
     */
    protected function generateTemporaryFileName($suffix)
    {
        do {
            $file = $this->database->getDirectory() 
                . DIRECTORY_SEPARATOR . uniqid('ipstack.v' . self::VERSION . '.') . '.' . $suffix
            ;
        } while (file_exists($file));
        return $file;
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
        $format = 'A'.$max;
        switch ($type) {
            case Database::TYPE_INT:
                $format = 'L';
                if ($min >= -2147483648 && $max <= 2147483647) $format = 'l';
                if ($min >= 0 && $max <= 65535) $format = 'I';
                if ($min >= -32768 && $max <= 32767) $format = 'i';
                if ($min >= 0 && $max <= 255) $format = 'C';
                if ($min >=-128 && $max <= 127) $format = 'c';
                break;
            case Database::TYPE_FLOAT:
                $format = 'f';
                break;
            case Database::TYPE_DOUBLE: // no break
            case Database::TYPE_NUMBER:
                $format = 'd';
                break;
        }
        return $format;
    }

    /**
     * @param string $register
     * @param array $relations
     */
    protected function addRegisterToRoot($register, $relations) {
        $info = $this->meta['registers'][$register];
        $f = $this->optimalPackFormat(Database::TYPE_INT, 0, $info['items']);
        $this->root['format']['pack'][$register] = $f;
        $this->root['format']['unpack'][$register] = $f.$register;
        $this->root['fields'][$register] = null;
        $this->root['values'][$register] = array(0);
        if (array_key_exists($register, $relations)) {
            foreach ($relations[$register] as $field=>$child) {
                $this->addRegisterToRoot($child, $relations);
            }
        }
    }

    /**
     * @param string $register
     * @param array $values
     * @param array $relations
     * @throws RegisterNotFound
     */
    protected function addFieldToRoot($register, $values, $relations) {
        if (!empty($register)) $this->root['fields'][$register] = array_pop($values);
        if (isset($relations[$register])) {
            $row = $this->database->getRegisterRow($register, $this->root['fields'][$register]);
            if (!$row) return;
            foreach ($relations[$register] as $field=>$child) {
                $cr = $this->database->getRegisterRow($child, $row[$field]);
                $this->addFieldToRoot($child, array($cr['_offset']), $relations);
            }
        }
    }

    /**
     * @param string $register
     * @param string $key
     * @param array $relations
     * @throws RegisterNotFound
     */
    protected function addValueToRoot($register, $key, $relations)
    {
        $this->root['values'][$register][] = $key;
        if (isset($relations[$register])) {
            $row = $this->database->getRegisterRow($register, $key);
            if (!$row) return;
            foreach ($relations[$register] as $field=>$child) {
                $cr = $this->database->getRegisterRow($child, $row[$field]);
                $this->addValueToRoot($child, $cr['_key'], $relations);
            }
        }
    }

    /**
     * @param string $register
     * @param string $key
     * @param array $relations
     * @throws RegisterNotFound
     */
    protected function removeValueFromRoot($register, $key, $relations)
    {
        $index = array_search($key, $this->root['values'][$register]);
        if ($index !== false) {
            unset($this->root['values'][$register][$index]);
        }
        if (isset($relations[$register])) {
            $row = $this->database->getRegisterRow($register, $key);
            if (!$row) return;
            foreach ($relations[$register] as $field=>$child) {
                $cr = $this->database->getRegisterRow($child, $row[$field]);
                $this->removeValueFromRoot($child, $cr['_key'], $relations);
            }
        }
    }
}