<?php
/** @noinspection SqlInsertValues */
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */

namespace Ipstack\Wizard\Database;

use Ipstack\Wizard\Exception\FieldNotFound;
use Ipstack\Wizard\Exception\IncorrectFieldName;
use Ipstack\Wizard\Exception\IncorrectFieldType;
use Ipstack\Wizard\Exception\IncorrectRegisterName;
use Ipstack\Wizard\Exception\RegisterNotFound;
use Ipstack\Wizard\Exception\RelationIsRecursive;
use Ipstack\Wizard\Exception\RowNotFound;
use PDO;
use PDOStatement;

class Database
{

    const TYPE_INT    = 'int';
    const TYPE_FLOAT  = 'float';
    const TYPE_DOUBLE = 'double';
    const TYPE_NUMBER = 'number';
    const TYPE_STRING = 'string';
    const TYPE_CHAR   = 'char';

    const ACTION_ADD    = 'add';
    const ACTION_REMOVE = 'remove';

    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var array
     */
    protected $meta = array(
        'rootRegisters' => array(),
        'registers' => array(),
        'relations' => array(),
        'limits' => array(),
        'author' => '',
        'license' => '',
        'time' => 0,
    );

    /**
     * @var PDOStatement[]
     */
    protected $statement = array();

    /**
     * @param string $directory
     */
    public function __construct($directory)
    {
        $this->directory = $directory;
        do {
            $file = $directory . DIRECTORY_SEPARATOR . uniqid() . 'tmp.sqlite';
        } while (file_exists($file));
        $file = $directory . DIRECTORY_SEPARATOR . 'db.sqlite';
        $this->file = $file;
        $this->pdo = new PDO('sqlite:' . $this->file);
        $this->pdo->exec('PRAGMA foreign_keys = 1;PRAGMA encoding = \'UTF-8\';');
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $sql = <<<SQL
CREATE TABLE `network` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `ip` INTEGER,
  `action` TEXT,
  `register` TEXT,
  `key` TEXT,
  `offset` TEXT
);
CREATE INDEX `ip` ON `network` (`ip`);
CREATE INDEX `parameter` ON `network` (`parameter`);
CREATE INDEX `value` ON `network` (`value`);
SQL;
        $this->pdo->exec($sql);
        try {
            $this->addInterval('0.0.0.0', '255.255.255.255', null, null);
        } catch (RegisterNotFound $e) {
        } catch (RowNotFound $e) {
        }
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * @param string $author
     * @return $this
     */
    public function setAuthor($author)
    {
        $this->meta['author'] = $author;
        return $this;
    }

    /**
     * @return string
     */
    public function getAuthor()
    {
        return $this->meta['author'];
    }

    /**
     * @param string $license
     * @return $this
     */
    public function setLicense($license)
    {
        $this->meta['license'] = $license;
        return $this;
    }

    /**
     * @return string
     */
    public function getLicense()
    {
        return $this->meta['license'];
    }

    /**
     * @param int $time
     * @return $this
     */
    public function setTime($time)
    {
        $this->meta['time'] = $time;
        return $this;
    }

    /**
     * @return int
     */
    public function getTime()
    {
        return $this->meta['time'];
    }

    /**
     * @param string $name
     * @param array $fields
     * @return $this
     * @throws IncorrectRegisterName
     * @throws IncorrectFieldName
     * @throws IncorrectFieldType
     */
    public function addRegister($name, $fields)
    {
        if (!$this->checkName($name)) {
            throw new IncorrectRegisterName($name);
        }
        foreach ($fields as $field => $type) {
            if (!$this->checkName($field)) {
                throw new IncorrectFieldName($name, $field);
            }
            if (!$this->checkType($type)) {
                throw new IncorrectFieldType($name, $field, $type);
            }
            $this->meta['limits'][$name][$field] = array('min'=>null, 'max'=>null, 'precision'=>0);
        }
        $this->meta['registers'][$name] = $fields;
        $sql = 'CREATE TABLE `register_' . $name . '` (`_key`, `_used`, `_offset`, `'
            . implode('`, `', array_keys($fields)) . '`, '
            . 'CONSTRAINT `_key` PRIMARY KEY (`_key`) ON CONFLICT IGNORE); '
            . 'CREATE INDEX `_used` ON `register_' . $name . '` (`_used`);';
        $this->pdo->exec($sql);
        return $this;
    }

    /**
     * @return array
     */
    public function getRegisters()
    {
        return $this->meta['registers'];
    }

    /**
     * @return array
     */
    public function getRootRegisters()
    {
        return array_values($this->meta['rootRegisters']);
    }

    /**
     * @param string $register
     * @return array
     * @throws RegisterNotFound
     */
    public function getRegister($register)
    {
        if (!isset($this->meta['registers'][$register])) {
            throw new RegisterNotFound($register);
        }
        return $this->meta['registers'][$register];
    }

    /**
     * @param string $register
     * @return array
     * @throws RegisterNotFound
     */
    public function getRegisterLimits($register)
    {
        if (!isset($this->meta['limits'][$register])) {
            throw new RegisterNotFound($register);
        }
        return $this->meta['limits'][$register];
    }

    /**
     * @param string $parent
     * @param string $field
     * @param string $child
     * @return $this
     * @throws FieldNotFound
     * @throws RegisterNotFound
     * @throws RelationIsRecursive
     */
    public function addRelation($parent, $field, $child)
    {
        if (!isset($this->meta['registers'][$parent])) {
            throw new RegisterNotFound($parent);
        }
        if (!isset($this->meta['registers'][$child])) {
            throw new RegisterNotFound($child);
        }
        if (!isset($this->meta['registers'][$parent][$field])) {
            throw new FieldNotFound($parent, $field);
        }
        foreach ($this->meta['relations'] as $p => $r) {
            foreach ($r as $f => $c) {
                if (isset($this->meta['relations'][$c]) && in_array($parent, $this->meta['relations'][$c])) {
                    throw new RelationIsRecursive($parent, $field, $child);
                }
            }
        }
        $this->meta['relations'][$parent][$field] = $child;
        return $this;
    }

    /**
     * @return array
     */
    public function getRelations()
    {
        return $this->meta['relations'];
    }

    /**
     * @param string $register
     * @param string $key
     * @param array $values
     * @return $this
     * @throws RegisterNotFound
     */
    public function addRow($register, $key, $values)
    {
        if (!array_key_exists($register, $this->meta['registers'])) {
            throw new RegisterNotFound($register);
        }
        $fields = $this->meta['registers'][$register];
        $data = array(
            '_key' => $key,
            '_used' => 0,
        );
        foreach ($fields as $field => $type) {
            $value = array_key_exists($field, $values) ? $values[$field] : null;
            $len = in_array($type, array(self::TYPE_STRING, self::TYPE_CHAR))?strlen($value):(float)$value;
            if (is_null($this->meta['limits'][$register][$field]['max'])) {
                $this->meta['limits'][$register][$field]['max'] = $len;
            }
            if (is_null($this->meta['limits'][$register][$field]['min'])) {
                $this->meta['limits'][$register][$field]['min'] = $len;
            }
            if ($len > $this->meta['limits'][$register][$field]['max']) {
                $this->meta['limits'][$register][$field]['max'] = $len;
            }
            if ($len < $this->meta['limits'][$register][$field]['min']) {
                $this->meta['limits'][$register][$field]['min'] = $len;
            }
            if ($type == self::TYPE_NUMBER) {
                $precision = 0;
                $pos = mb_strpos((string)$value, '.');
                if ($pos !== false) {
                    $pos++;
                    $precision = mb_strlen((string)$value) - $pos;
                }
                if ($precision > $this->meta['limits'][$register][$field]['precision']) {
                    $this->meta['limits'][$register][$field]['precision'] = $precision;
                }
            }
            $data[$field] = $value;
        }
        $sql = 'INSERT INTO `register_' . $register . '` (`' . implode('`, `', array_keys($data)) . '`)'
            . ' VALUES (:' . implode(', :', array_keys($data)) . ');';
        $st = $this->getStatement($sql);
        $st->execute($data);
        return $this;
    }

    /**
     * @param string $firstIp
     * @param string $lastIp
     * @param string $register
     * @param string $key
     * @return $this
     * @throws RegisterNotFound
     * @throws RowNotFound
     */
    public function addInterval($firstIp, $lastIp, $register, $key)
    {
        if (!is_null($register) && !array_key_exists($register, $this->meta['registers'])) {
            throw new RegisterNotFound($register);
        }
        if (!is_null($register) && !$this->checkRegisterKey($register, $key)) {
            throw new RowNotFound($register, $key);
        }
        $sql = 'INSERT INTO `network` (`ip`, `action`, `register`, `key`, `offset`) VALUES '
            . '(:ip, :action, :register, :key, :offset);';
        $st = $this->getStatement($sql);
        $ip = ip2long($firstIp);
        $data = array(
            'ip' => $ip,
            'action' => self::ACTION_ADD,
            'register' => $register,
            'key' => $key,
            'offset' => 0,
        );
        if (!is_null($register)) $this->meta['rootRegisters'][$register] = $register;
        $st->execute($data);
        $ip = ip2long($lastIp);
        if ($ip < ip2long('255.255.255.255')) {
            $ip++;
        } else {
            $ip = ip2long('255.255.255.255');
        }
        $data['ip'] = $ip;
        $data['action'] = self::ACTION_REMOVE;
        $st->execute($data);
        if (!is_null($register)) {
            $sql = 'UPDATE `register_' . $register . '` SET `_used` = :_used WHERE `_key` = :_key;';
            $st = $this->getStatement($sql);
            $st->execute(array('_key' => $key, '_used' => 1));
        }
        return $this;
    }

    /**
     * @param string $register
     * @param string $key
     * @param string $offset
     * @return $this
     * @throws RegisterNotFound
     */
    public function setOffset($register, $key, $offset)
    {
        if (!isset($this->meta['registers'][$register])) {
            throw new RegisterNotFound($register);
        }
        $sql = 'UPDATE `register_' . $register . '` SET `_offset` = :_offset WHERE `_key` = :_key;';
        $st = $this->getStatement($sql);
        $st->execute(array('_offset' => $offset, '_key' => $key));
        $sql = 'UPDATE `network` SET `offset` = :offset '
            . 'WHERE `key` = :key AND `register` = :register AND `action` = :action;';
        $st = $this->getStatement($sql);
        $st->execute(array(
            'offset' => $offset,
            'key' => $key,
            'register' => $register,
            'action' => self::ACTION_ADD,
        ));
        return $this;

    }

    /**
     * @param void
     * @return $this
     */
    public function clear()
    {
        foreach ($this->meta['relations'] as $register => $relation) {
            $group = array();
            foreach (array_keys($relation) as $field) {
                $group[] = $field;
            }
            $sql = 'SELECT * FROM `register_' . $register . '` WHERE `_used` = :_used GROUP BY `' . implode('`, `', $group) . '`';
            $st = $this->getStatement($sql);
            $st->execute(array('_used' => 1));
            while ($row = $st->fetch()) {
                foreach ($relation as $field => $child) {
                    $sql = 'UPDATE `register_' . $child . '` SET `_used` = :_used WHERE `_key` = :_key;';
                    $st2 = $this->getStatement($sql);
                    $st2->execute(array('_key' => $row[$field], '_used' => $row['_used']));
                }
            }
        }
        foreach ($this->meta['registers'] as $register => $null) {
            $sql = 'DELETE FROM `register_' . $register . '` WHERE `_used`=:_used;';
            $st = $this->getStatement($sql);
            $st->execute(array('_used' => 0));
        }
        return $this;
    }

    /**
     * @param string $register
     * @return Rows
     * @throws RegisterNotFound
     */
    public function getRegisterRows($register)
    {
        if (!isset($this->meta['registers'][$register])) {
            throw new RegisterNotFound($register);
        }
        $sql = 'SELECT * FROM `register_' . $register . '`;';
        $st = $this->getStatement($sql);
        $st->execute();
        return new Rows($st);
    }

    /**
     * @param string $register
     * @param string $key
     * @return null|array
     * @throws RegisterNotFound
     */
    public function getRegisterRow($register, $key)
    {
        if (!isset($this->meta['registers'][$register])) {
            throw new RegisterNotFound($register);
        }
        $sql = 'SELECT * FROM `register_' . $register . '` WHERE `_key` = :key;';
        $st = $this->getStatement($sql);
        $st->execute(array('key' => $key));
        return $st->fetch();
    }

    /**
     * @return Rows
     */
    public function getNetworkRows()
    {
        $sql = 'SELECT * FROM `network` ORDER BY `ip` ASC, `action` DESC, `id` ASC;';
        $st = $this->getStatement($sql);
        $st->execute();
        return new Rows($st);
    }

    /**
     * @void
     */
    public function __destruct()
    {
        $this->pdo = null;
        if (file_exists($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    protected function checkName($name)
    {
        return preg_match('/^(\p{L}[0-9\p{L}\._\-]+)$/ui', $name)?true:false;
    }

    /**
     * @param string $type
     * @return bool
     */
    protected function checkType($type)
    {
        if (in_array($type, array(
            self::TYPE_INT,
            self::TYPE_FLOAT,
            self::TYPE_DOUBLE,
            self::TYPE_NUMBER,
            self::TYPE_CHAR,
            self::TYPE_STRING,
        ))) {
            return true;
        }
        return false;
    }

    /**
     * @param string $register
     * @param string $key
     * @return bool
     * @throws RegisterNotFound
     */
    protected function checkRegisterKey($register, $key)
    {
        if (!isset($this->meta['registers'][$register])) {
            throw new RegisterNotFound($register);
        }
        $sql = 'SELECT * FROM `register_' . $register . '` WHERE `_key` = :_key';
        $st = $this->getStatement($sql);
        $st->execute(array('_key'=>$key));
        $r = $st->fetch();
        return $r?true:false;
    }

    /**
     * @param string $sql
     * @return PDOStatement
     */
    protected function getStatement($sql)
    {
        $key = md5($sql);
        if (!array_key_exists($key, $this->statement) || !($this->statement[$key] instanceof PDOStatement)) {
            $this->statement[$key] = $this->pdo->prepare($sql);
        }
        return $this->statement[$key];
    }
}