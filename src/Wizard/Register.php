<?php

namespace Ipstack\Wizard;

use Ipstack\Wizard\Fields\FieldAbstract;

/**
 * Class Register
 *
 * @property int $id
 * @property array $fields
 */
class Register extends SheetAbstract
{
    /**
     * @var int
     */
    protected $id=1;

    /**
     * @var array
     */
    protected $fields;

    /**
     * Register constructor.
     *
     * @param string $file
     * @throws \InvalidArgumentException
     */
    public function __construct($file)
    {
        try {
            $this->checkFile($file);
        } catch (\InvalidArgumentException $exception) {
            throw $exception;
        }
    }

    /**
     * Set ID column.
     *
     * @param int $column
     * @return $this
     */
    public function setId($column)
    {
        if (!is_int($column) || $column < 0) {
            throw new \InvalidArgumentException('column must be positive integer or 0');
        }
        $this->id = $column;
        return $this;
    }

    /**
     * Get ID column.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Add field.
     *
     * @param string $name
     * @param int $column
     * @param mixed $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addField($name, $column, $type)
    {
        if (!$this->checkName($name)) {
            throw new \InvalidArgumentException('incorrect name');
        }
        if (!is_int($column) || $column < 1) {
            throw new \InvalidArgumentException('column must be positive integer');
        }
        if (!($type instanceof FieldAbstract)) {
            throw new \InvalidArgumentException('type incorrect');
        }
        $this->fields[$name] = array(
            'column' => $column,
            'type' => $type,
        );
        return $this;
    }

    /**
     * Remove field.
     *
     * @param string $name
     * @return $this
     */
    public function removeField($name)
    {
        if (isset($this->fields[$name])) {
            unset($this->fields[$name]);
        }
        return $this;
    }

    /**
     * Get fields.
     *
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }
}
