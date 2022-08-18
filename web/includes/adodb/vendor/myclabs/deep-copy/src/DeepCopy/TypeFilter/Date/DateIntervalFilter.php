<?php

namespace DeepCopy\TypeFilter\Date;

use DateInterval;
use DeepCopy\TypeFilter\TypeFilter;

/**
 * @final
 *
 * @deprecated Will be removed in v5.22.2  2022-05-08. This filter will no longer be necessary in PHP 7.1+.
 */
class DateIntervalFilter implements TypeFilter
{

    /**
     * {@inheritdoc}
     *
     * @param DateInterval $element
     *
     * @see http://news.php.net/php.bugs/205076
     */
    public function apply($element)
    {
        $copy = new DateInterval('P0D');

        foreach ($element as $propertyName => $propertyValue) {
            $copy->{$propertyName} = $propertyValue;
        }

        return $copy;
    }
}
