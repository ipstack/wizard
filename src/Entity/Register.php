<?php

namespace Ipstack\Wizard\Entity;
use Ipstack\Wizard\Field\StringField;

/**
 * Class Register
 *
 * @property int $id
 * @property array $fields
 */
class Register extends EntityAbstract
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
     * Add string field.
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
        if (!is_int($maxLength) || $maxLength < 0) {
            throw new \InvalidArgumentException('maxLength must be positive integer or 0');
        }
        switch ($transform) {
            case StringField::TRANSFORM_NONE:
                $type = 'sT';
                break;
            case StringField::TRANSFORM_LOWER:
                $type = 'st';
                break;
            case StringField::TRANSFORM_UPPER:
                $type = 'ST';
                break;
            case StringField::TRANSFORM_TITLE:
                $type = 'St';
                break;
            default:
                throw new \InvalidArgumentException('transform incorrect');
                break;
        }
        if ($maxLength) $type .= $maxLength;
        return $this->addField($name, $column, $type);
    }

    /**
     * Add numeric field.
     *
     * @param string $name
     * @param int $column
     * @param int $precision
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addNumericField($name, $column, $precision=0)
    {
        if (!is_int($precision) || $precision < 0) {
            throw new \InvalidArgumentException('precision must be positive integer or 0');
        }
        $type = 'n';
        if ($precision) $type .= $precision;
        return $this->addField($name, $column, $type);
    }

    /**
     * Add float field.
     *
     * @param string $name
     * @param int $column
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addFloatField($name, $column)
    {
        return $this->addField($name, $column, 'f');
    }

    /**
     * Add latitude field.
     *
     * @param string $name
     * @param int $column
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addLatitudeField($name, $column)
    {
        return $this->addField($name, $column, 'lat');
    }

    /**
     * Add longitude field.
     *
     * @param string $name
     * @param int $column
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addLongitudeField($name, $column)
    {
        return $this->addField($name, $column, 'lon');
    }

    /**
     * Add field (Universal method)
     *
     * @param string $name
     * @param int $column
     * @param string $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addField($name, $column, $type='sT')
    {
        if (!$this->checkName($name)) {
            throw new \InvalidArgumentException('incorrect name');
        }
        if (!is_int($column) || $column < 1) {
            throw new \InvalidArgumentException('column must be positive integer');
        }
        $regexp = '/^(?<type>(st|n|f|lat|lon))(?<number>\d+)?/ui';
        preg_match($regexp, $type, $math);
        if (empty($math['type'])) {
            throw new \InvalidArgumentException('incorrect type');
        }
        $numberKey = null;
        $number = empty($math['number'])?0:$math['number'];
        $data = array(
            'column' => $column,
            'type' => 'string',
        );
        switch ($math['type']) {
            case 'st':
                $data['transform'] = StringField::TRANSFORM_LOWER;
                $numberKey = 'maxLength';
                break;
            case 'ST':
                $data['transform'] = StringField::TRANSFORM_UPPER;
                $numberKey = 'maxLength';
                break;
            case 'St':
                $data['transform'] = StringField::TRANSFORM_TITLE;
                $numberKey = 'maxLength';
                break;
            case 'sT':
                $data['transform'] = StringField::TRANSFORM_NONE;
                $numberKey = 'maxLength';
                break;
            case 'n':
                $data['type'] = 'numeric';
                $numberKey = 'precision';
                break;
            case 'f':
                $data['type'] = 'float';
                break;
            case 'lat':
                $data['type'] = 'numeric';
                $number = 4;
                $numberKey = 'precision';
                $data['min'] = -90;
                $data['max'] = 90;
                break;
            case 'lon':
                $data['type'] = 'numeric';
                $number = 4;
                $numberKey = 'precision';
                $data['min'] = -180;
                $data['max'] = 180;
                break;
            default:
                break;
        }
        if ($number && $numberKey) $data[$numberKey] = (int)$number;
        $this->fields[$name] = $data;
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
