<?php

namespace Elemenx\Cron;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use RuntimeException;
use InvalidArgumentException;

/**
 * Update to original package (@link https://github.com/mtdowling/cron-expression) to be able to process sub-minute tasks
 *
 * @author Dejan Samardzija <samardzija.dejan@gmail.com>
 */
class CronExpression extends \Cron\CronExpression
{
    const SECOND = 0;
    const MINUTE = 1;
    const HOUR = 2;
    const DAY = 3;
    const MONTH = 4;
    const WEEKDAY = 5;
    const YEAR = 6;

    /**
     * @var array CRON expression parts
     */
    private $cronParts;

    /**
     * @var FieldFactory CRON field factory
     */
    private $fieldFactory;

    /**
     * @var int Max iteration count when searching for next run date
     */
    private $maxIterationCount = 1000;

    /**
     * @var array Order in which to test of cron parts
     */
    private static $order = [self::YEAR, self::MONTH, self::DAY, self::WEEKDAY, self::HOUR, self::MINUTE, self::SECOND];

    /**
     * Factory method to create a new CronExpression.
     *
     * @param string $expression The CRON expression to create.  There are
     *      several special predefined values which can be used to substitute the
     *      CRON expression:
     *
     *      @yearly, @annually) - Run once a year, midnight, Jan. 1 - 0 0 1 1 *
     *      @monthly - Run once a month, midnight, first of month - 0 0 1 * *
     *      @weekly - Run once a week, midnight on Sun - 0 0 * * 0
     *      @daily - Run once a day, midnight - 0 0 * * *
     *      @hourly - Run once an hour, first minute - 0 * * * *
     * @param FieldFactory $fieldFactory (optional) Field factory to use
     *
     * @return CronExpression
     */
    public static function factory($expression, \Cron\FieldFactory $fieldFactory = null)
    {
        $mappings = [
            '@yearly'   => '0 0 0 1 1 *',
            '@annually' => '0 0 0 1 1 *',
            '@monthly'  => '0 0 0 1 * *',
            '@weekly'   => '0 0 0 * * 0',
            '@daily'    => '0 0 0 * * *',
            '@hourly'   => '0 0 * * * *',
            '@minutely' => '0 * * * * *' // adj. Archaic: On a minute-by-minute basis. @see http://www.thefreedictionary.com/minutely
        ];

        if (isset($mappings[$expression])) {
            $expression = $mappings[$expression];
        }

        return new static($expression, $fieldFactory ?: new \Elemenx\Cron\FieldFactory());
    }

    /**
     * Parse a CRON expression
     *
     * @param string       $expression   CRON expression (e.g. '8 * * * *')
     * @param FieldFactory $fieldFactory Factory to create cron fields
     */
    public function __construct($expression, \Cron\FieldFactory $fieldFactory)
    {
        if (!$fieldFactory instanceof \Elemenx\Cron\FieldFactory) {
            throw new InvalidArgumentException('This is updated package that requires \Elemenx\Cron\FieldFactory to work, using original one will break execution');
        }

        $this->fieldFactory = $fieldFactory;
        $this->setExpression($expression);
    }

    /**
     * Validate a CronExpression.
     *
     * @param string $expression The CRON expression to validate.
     *
     * @return bool True if a valid CRON expression was passed. False if not.
     * @see \Cron\CronExpression::factory
     */
    public static function isValidExpression($expression)
    {
        try {
            self::factory($expression);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    /**
     * Set or change the CRON expression
     *
     * @param string $schedule CRON expression (e.g. 8 * * * *)
     *
     * @return CronExpression
     * @throws InvalidArgumentException if not a valid CRON expression
     */
    public function setExpression($value)
    {
        $this->cronParts = preg_split('/\s/', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (count($this->cronParts) < 6) {
            throw new InvalidArgumentException(
                $value . ' is not a valid CRON expression'
            );
        }

        foreach ($this->cronParts as $position => $part) {
            $this->setPart($position, $part);
        }

        return $this;
    }

    /**
     * Set part of the CRON expression
     *
     * @param int    $position The position of the CRON expression to set
     * @param string $value    The value to set
     *
     * @return CronExpression
     * @throws InvalidArgumentException if the value is not valid for the part
     */
    public function setPart($position, $value)
    {
        if (!$this->fieldFactory->getField($position)->validate($value)) {
            throw new InvalidArgumentException(
                'Invalid CRON field value ' . $value . ' as position ' . $position
            );
        }

        $this->cronParts[$position] = $value;

        return $this;
    }

    /**
     * Get all or part of the CRON expression
     *
     * @param string $part (optional) Specify the part to retrieve or NULL to
     *      get the full cron schedule string.
     *
     * @return string|null Returns the CRON expression, a part of the
     *      CRON expression, or NULL if the part was specified but not found
     */
    public function getExpression($part = null)
    {
        if (null === $part) {
            return implode(' ', $this->cronParts);
        } elseif (array_key_exists($part, $this->cronParts)) {
            return $this->cronParts[$part];
        }

        return null;
    }

    /**
     * Deterime if the cron is due to run based on the current date or a
     * specific date.  This method assumes that the current number of
     * seconds are irrelevant, and should be called once per minute.
     *
     * @param string|DateTime $currentTime (optional) Relative calculation date
     *
     * @return bool Returns TRUE if the cron is due to run or FALSE if not
     */
    public function isDue($currentTime = 'now', $timeZone = null)
    {
        $timeZone = $this->determineTimeZone($currentTime, $timeZone);

        if ('now' === $currentTime) {
            $currentTime = new DateTime();
        } elseif ($currentTime instanceof DateTime) {
            //
        } elseif ($currentTime instanceof DateTimeImmutable) {
            $currentTime = DateTime::createFromFormat('U', $currentTime->format('U'));
        } else {
            $currentTime = new DateTime($currentTime);
        }

        $currentTime->setTimeZone(new DateTimeZone($timeZone));

        try {
            return $this->getNextRunDate($currentTime, 0, true)->getTimestamp() === $currentTime->getTimestamp();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the next or previous run date of the expression relative to a date
     *
     * @param string|DateTime $currentTime      (optional) Relative calculation date
     * @param int             $nth              (optional) Number of matches to skip before returning
     * @param bool            $invert           (optional) Set to TRUE to go backwards in time
     * @param bool            $allowCurrentDate (optional) Set to TRUE to return the
     *     current date if it matches the cron expression
     *
     * @return DateTime
     * @throws RuntimeExpression on too many iterations
     */
    protected function getRunDate($currentTime = null, $nth = 0, $invert = false, $allowCurrentDate = false, $timeZone = null)
    {
        $timeZone = $this->determineTimeZone($currentTime, $timeZone);

        if ($currentTime instanceof DateTime) {
            $currentDate = clone $currentTime;
        } elseif ($currentTime instanceof DateTimeImmutable) {
            $currentDate = DateTime::createFromFormat('U', $currentTime->format('U'));
        } else {
            $currentDate = new DateTime($currentTime ?: 'now');
        }

        $currentDate->setTimeZone(new DateTimeZone($timeZone));
        $currentDate->setTime($currentDate->format('H'), $currentDate->format('i'), $currentDate->format('s'));
        $nextRun = clone $currentDate;
        $nth = (int) $nth;

        // We don't have to satisfy * or null fields
        $parts = [];
        $fields = [];
        foreach (self::$order as $position) {
            $part = $this->getExpression($position);
            if (null === $part || '*' === $part) {
                continue;
            }
            $parts[$position] = $part;
            $fields[$position] = $this->fieldFactory->getField($position);
        }

        // Set a hard limit to bail on an impossible date
        for ($i = 0; $i < $this->maxIterationCount; $i++) {
            foreach ($parts as $position => $part) {
                $satisfied = false;
                // Get the field object used to validate this part
                $field = $fields[$position];
                // Check if this is singular or a list
                if (strpos($part, ',') === false) {
                    $satisfied = $field->isSatisfiedBy($nextRun, $part);
                } else {
                    foreach (array_map('trim', explode(',', $part)) as $listPart) {
                        if ($field->isSatisfiedBy($nextRun, $listPart)) {
                            $satisfied = true;
                            break;
                        }
                    }
                }

                // If the field is not satisfied, then start over
                if (!$satisfied) {
                    $field->increment($nextRun, $invert, $part);
                    continue 2;
                }
            }

            // Skip this match if needed
            if ((!$allowCurrentDate && $nextRun == $currentDate) || --$nth > -1) {
                $this->fieldFactory->getField(0)->increment($nextRun, $invert, isset($parts[0]) ? $parts[0] : null);
                continue;
            }

            return $nextRun;
        }

        // @codeCoverageIgnoreStart
        throw new RuntimeException('Impossible CRON expression');
        // @codeCoverageIgnoreEnd
    }
}
