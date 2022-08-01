<?php

namespace Elemenx\Cron;

use Cron\AbstractField;
use DateTimeInterface;

/**
 * Minutes field.  Allows: * , / -
 *
 * @author Michael Dowling <mtdowling@gmail.com>
 */
class SecondsField extends AbstractField
{
    /**
     * @inheritDoc
     */
    protected $rangeStart = 0;

    /**
     * @inheritDoc
     */
    protected $rangeEnd = 59;

    /**
     * {@inheritdoc}
     */
    public function isSatisfiedBy(DateTimeInterface $date, $value)
    {
        return $this->isSatisfied($date->format('s'), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function increment(DateTimeInterface &$date, $invert = false, $parts = null)
    {
        if (is_null($parts)) {
            $date = $date->modify(($invert ? '-' : '+') . '1 second');
            return $this;
        }

        $parts = strpos($parts, ',') !== false ? explode(',', $parts) : [$parts];
        $seconds = [];

        foreach ($parts as $part) {
            $seconds = array_merge($seconds, $this->getRangeForExpression($part, 59));
        }

        $currnet_second = $date->format('s');
        $position = $invert ? count($seconds) - 1 : 0;
        if (count($seconds) > 1) {
            for ($i = 0; $i < count($seconds) - 1; $i++) {
                if ((!$invert && $currnet_second >= $seconds[$i] && $currnet_second < $seconds[$i + 1]) ||
                    ($invert && $currnet_second > $seconds[$i] && $currnet_second <= $seconds[$i + 1])) {
                    $position = $invert ? $i : $i + 1;
                    break;
                }
            }
        }
        if ((!$invert && $currnet_second >= $seconds[$position]) || ($invert && $currnet_second <= $seconds[$position])) {
            $date = $date->modify(($invert ? '-' : '+') . '1 minute');
            $date = $date->setTime($date->format('H'), $date->format('i'), $invert ? 59 : 0);
        } else {
            $date = $date->setTime($date->format('H'), $date->format('i'), $seconds[$position]);
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
