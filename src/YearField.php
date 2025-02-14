<?php

namespace Elemenx\Cron;

use Cron\AbstractField;
use DateTimeInterface;

/**
 * Minutes field.  Allows: * , / -
 *
 * @author Michael Dowling <mtdowling@gmail.com>
 */
class YearField extends AbstractField
{
    /**
     * {@inheritdoc}
     */
    public function isSatisfiedBy(DateTimeInterface $date, $value)
    {
        if ($value == '?') {
            return true;
        }

        return $this->isSatisfied($date->format('Y'), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function increment(DateTimeInterface &$date, $invert = false)
    {
        if ($invert) {
            $date->modify('-1 year');
            $date->setDate($date->format('Y'), 12, 31);
            $date->setTime(23, 59, 0);
        } else {
            $date->modify('+1 year');
            $date->setDate($date->format('Y'), 1, 1);
            $date->setTime(0, 0, 0);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value)
    {
        return (bool) preg_match('/[\*,\/\-0-9]+/', $value);
    }
}
