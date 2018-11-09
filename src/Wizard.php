<?php

namespace Ipstack\Wizard;

use Ipstack\Wizard\Builder\BuilderInterface;
use Ipstack\Wizard\Builder\IpstackVersion1;
use Ipstack\Wizard\Database\Database;
use Ipstack\Wizard\Exception\FieldNotFound;
use Ipstack\Wizard\Exception\IncorrectFieldName;
use Ipstack\Wizard\Exception\IncorrectFieldType;
use Ipstack\Wizard\Exception\IncorrectRegisterName;
use Ipstack\Wizard\Exception\RegisterNotFound;
use Ipstack\Wizard\Exception\RelationIsRecursive;
use Ipstack\Wizard\Exception\RowNotFound;

class Wizard
{

    const FORMAT_IPSTACK_V1 = 'ipstack-v1';

    const TYPE_INT    = Database::TYPE_INT;
    const TYPE_FLOAT  = Database::TYPE_FLOAT;
    const TYPE_DOUBLE = Database::TYPE_DOUBLE;
    const TYPE_NUMBER = Database::TYPE_NUMBER;
    const TYPE_STRING = Database::TYPE_STRING;
    const TYPE_CHAR   = Database::TYPE_CHAR;

    /**
     * @var Database
     */
    protected $database;

    /**
     * @param null|string $temporaryDirectory
     */
    public function __construct($temporaryDirectory = null)
    {
        if (is_null($temporaryDirectory)) $temporaryDirectory = sys_get_temp_dir();
        $this->database = new Database($temporaryDirectory);
    }

    /**
     * @param string $author
     */
    public function setAuthor($author)
    {
        $this->database->setAuthor($author);
    }

    /**
     * @param string $license
     */
    public function setLicense($license)
    {
        $this->database->setLicense($license);
    }

    /**
     * @param int $time
     */
    public function setTime($time)
    {
        $this->database->setTime($time);
    }

    /**
     * @param string $name
     * @param array $fields
     * @return $this
     * @throws IncorrectFieldName
     * @throws IncorrectFieldType
     * @throws IncorrectRegisterName
     */
    public function addRegister($name, $fields)
    {
        $this->database->addRegister($name, $fields);
        return $this;
    }

    /**
     * @param $parent
     * @param $field
     * @param $child
     * @return $this
     * @throws FieldNotFound
     * @throws RelationIsRecursive
     * @throws RegisterNotFound
     */
    public function addRelation($parent, $field, $child)
    {
        $this->database->addRelation($parent, $field, $child);
        return $this;
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
        $this->database->addRow($register, $key, $values);
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
        $this->database->addInterval($firstIp, $lastIp, $register, $key);
        return $this;
    }

    /**
     * @param string $version
     * @param string $file
     * @throws RegisterNotFound
     * @return $this
     */
    public function build($version, $file)
    {
        $this->database->clear();
        switch ($version) {
            case self::FORMAT_IPSTACK_V1:
                $builder = new IpstackVersion1($this->database);
                break;
            default:
                $builder = null;
                break;
        }
        if ($builder instanceof BuilderInterface) {
            $builder->build($file);
        }
        return $this;
    }
}