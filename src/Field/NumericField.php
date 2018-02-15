<?php

namespace Ipstack\Wizard\Field;

use Ddrv\Extra\Pack;

/**
 * Class NumericField
 *
 * @property array $settings
 * @property array $values
 */
class NumericField extends FieldAbstract
{
    /**
     * @var array
     */
    protected $settings=array(
        'max' => null,
        'min' => null,
        'precision' => 0,
    );

    /**
     * @var array
     */
    protected $values=array(
        'min' => 0,
        'max' => 0,
    );

    /**
     * Get valid value.
     *
     * @param $value
     * @return mixed
     */
    public function validValue($value=null)
    {
        $delta = pow(10,$this->settings['precision']);
        $value = intval($value*$delta)/$delta;
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

    /**
     * Return pack format.
     */
    public function getFormat()
    {
        Pack::getOptimalFormat(
            $this->values['min'],
            $this->values['max'],
            $this->format['key'],
            $this->settings['precision']
        );
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