<?php

namespace Ipstack\Wizard\Field;

/**
 * Class FieldAbstract
 *
 * @property array $settings
 * @property array $values
 * @property array $format
 */
abstract class FieldAbstract
{
    /**
     * @var array
     */
    protected $settings;

    /**
     * @var array
     */
    protected $values;

    /**
     * @var array
     */
    protected $format = array(
        'key' => '',
        'character' => 'A',
        'number' => '1',
        'added' => 0,
    );

    /**
     * FieldAbstract constructor.
     *
     * @param string $key
     * @param array $settings
     */
    public function __construct($key, $settings=array())
    {
        $this->format['key'] = $key;
        $this->settings = array_replace($this->settings, $settings);
    }

    /**
     * Get valid value.
     *
     * @param $value
     * @return mixed
     */
    public function validValue($value=null)
    {
        $this->update($value);
        return $value;
    }

    /**
     * @param $value
     * @return bool
     */
    public function update($value=null)
    {
        return true;
    }

    /**
     * Return pack format.
     */
    public function getFormat()
    {
        $format = $this->format['character'];
        if (!empty($this->format['number'])) {
            $format .= $this->format['number'];
        }
        $format .= $this->format['key'];
        if (!empty($this->format['added'])) {
            $format .= ':'.$this->format['added'];
        }
        return $format;
    }
}