<?php
/**
 * @file
 */

namespace AppBundle\Services;

use AppBundle\Exception\RestException;

/**
 * More precise alias against PHP's built-in date format.
 */
define('ISO8601', 'c');

/**
 * Class RestHelper
 *
 * @package AppBundle\Services
 */
class RestHelper
{
    private $timeZone = null;

    /**
     * RestHelper constructor.
     *
     * @param string $timeZone
     *  PHP variant of timezone string.
     *
     * @throws \AppBundle\Exception\RestException
     */
    public function __construct($timeZone)
    {
        try {
            $this->timeZone = new \DateTimeZone($timeZone);
        }
        catch (\Exception $e) {
            throw new RestException('Could not parse timezone string.');
        }
    }

    /**
     * Returns the timezone object.
     *
     * @return \DateTimeZone|null
     */
    public function getTimeZone()
    {
        return $this->timeZone;
    }

    /**
     * Adjusts the input timestamp to desired date format,
     * taking into consideration configured timezone.
     *
     * @param int $timeStamp
     * @param string $format
     *
     * @return string
     *   Date in requested format
     * @throws \AppBundle\Exception\RestException
     */
    public function adjustDate($timeStamp, $format = ISO8601)
    {
        if (!is_integer($timeStamp)) {
            throw new RestException('Argument must be a valid unix timestamp.');
        }

        $dateTime = new \DateTime();
        $dateTime->setTimestamp($timeStamp);
        $dateTime->setTimezone($this->getTimeZone());

        return $dateTime->format($format);
    }
}
