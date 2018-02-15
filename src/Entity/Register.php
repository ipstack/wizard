<?php

namespace Ipstack\Wizard\Entity;

/**
 * Class Register
 *
 * @const int STRING_TRANSFORM_NONE
 * @const int STRING_TRANSFORM_LOWER
 * @const int STRING_TRANSFORM_UPPER
 * @const int STRING_TRANSFORM_TITLE
 * @property int $id
 * @property array $fields
 */
class Register extends EntityAbstract
{

    /**
     * @const int
     */
    const STRING_TRANSFORM_NONE = 0;

    /**
     * @const int
     */
    const STRING_TRANSFORM_LOWER = 1;

    /**
     * @const int
     */
    const STRING_TRANSFORM_UPPER = 2;

    /**
     * @const int
     */
    const STRING_TRANSFORM_TITLE = 3;

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
     * @param int $transform
     * @param int $maxLength
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addStringField($name, $column, $transform=0, $maxLength=0)
    {
        if (!$this->checkName($name)) {
            throw new \InvalidArgumentException('incorrect name');
        }
        if (!is_int($column) || $column < 1) {
            throw new \InvalidArgumentException('column must be positive integer');
        }
        if (!is_int($maxLength) || $maxLength < 0) {
            throw new \InvalidArgumentException('maxLength must be positive integer or 0');
        }
        if (
            !in_array(
                $transform,
                array(
                    self::STRING_TRANSFORM_NONE,
                    self::STRING_TRANSFORM_LOWER,
                    self::STRING_TRANSFORM_UPPER,
                    self::STRING_TRANSFORM_TITLE
                )
            )
        ) {
            throw new \InvalidArgumentException('transform incorrect');
        }
        $this->fields[$name] = array(
            'column' => $column,
            'type' => 'string',
            'transform' => $transform,
            'maxLength' => $maxLength,
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
