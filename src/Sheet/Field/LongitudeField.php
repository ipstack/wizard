<?php

namespace Ipstack\Wizard\Sheet\Field;

/**
 * Class LongitudeField
 *
 * @property double $min
 * @property double $max
 */
class LongitudeField extends CoordinateFieldAbstract
{
    /**
     * @var double
     */
    protected $min = -180.0;

    /**
     * @var double
     */
    protected $max = 180.0;
}