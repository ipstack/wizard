<?php

namespace Ipstack\Wizard\Field;

/**
 * Class FloatField
 *
 * @property array $format
 * @property array $values
 * @property array $settings
 */
class FloatField extends FieldAbstract
{
    /**
     * @var array
     */
    protected $settings=array(
        'max' => null,
        'min' => null,
    );

    /**
     * @var array
     */
    protected $values=array(
        'min' => 0,
        'max' => 0,
    );

    /**
     * @var array
     */
    protected $format = array(
        'key' => '',
        'character' => 'd',
        'number' => null,
        'added' => null,
    );

    /**
     * Get valid value.
     *
     * @param $value
     * @return mixed
     */
    public function validValue($value=null)
    {
        if (!is_null($this->settings['max']) && $value > $this->settings['max']) {
            $value = $this->settings['max'];
        }
        if (!is_null($this->settings['min']) && $value < $this->settings['min']) {
            $value = $this->settings['min'];
        }
        $this->update($value);
        return $value;
    }

    /**
     * @param $value
     * @return bool
     */
    public function update($value=null)
    {
        if ($this->values['min'] > $value) {
            $this->values['min'] = $value;
        }
        if ($this->values['max'] < $value) {
            $this->values['max'] = $value;
        }
        return true;
    }
}