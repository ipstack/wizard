<?php

namespace Ipstack\Wizard\Field;

/**
 * Class StringField
 *
 * @const int TRANSFORM_NONE
 * @const int TRANSFORM_LOWER
 * @const int TRANSFORM_UPPER
 * @const int TRANSFORM_TITLE
 * @property array $settings
 */
class StringField extends FieldAbstract
{
    /**
     * @const int
     */
    const TRANSFORM_NONE = 0;

    /**
     * @const int
     */
    const TRANSFORM_LOWER = 1;

    /**
     * @const int
     */
    const TRANSFORM_UPPER = 2;

    /**
     * @const int
     */
    const TRANSFORM_TITLE = 3;

    /**
     * @var array
     */
    protected $settings=array(
        'transform' => self::TRANSFORM_NONE,
        'maxLength' => 0,
    );

    /**
     * StringField constructor.
     *
     * @param string $key
     * @param array $settings
     */
    public function __construct($key, $settings=array())
    {
        parent::__construct($key, $settings);
        if ($this->settings['maxLength'] == '~') {
            $this->format['character'] = '~';
            $this->format['number'] = null;
        }
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
        if (
            !empty($this->settings['maxLength'])
            && $this->settings['maxLength'] != '~'
            && strlen($value) > $this->settings['maxLength']
        ) {
            $value = substr($value, 0, $this->settings['maxLength']);
        }
        if ($this->settings['transform'] !== self::TRANSFORM_NONE) {
            switch ($this->settings['transform']) {
                case self::TRANSFORM_LOWER:
                    $value = mb_convert_case($value, \MB_CASE_LOWER);
                    break;
                case self::TRANSFORM_UPPER:
                    $value = mb_convert_case($value, \MB_CASE_UPPER);
                    break;
                case self::TRANSFORM_TITLE:
                    $value = mb_convert_case($value, \MB_CASE_TITLE);
                    break;
            }
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
        if (
            $this->settings['maxLength'] != '~'
            && $this->format['number'] < strlen($value)
        ) {
            $this->format['number'] = strlen($value);
        }
        return true;
    }
}