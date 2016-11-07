<?php
/**
 * Lauper Computing
 * User: Michael Hadorn <michael.hadorn@laupercomputing.ch>
 * Date: 02/10/14
 * Time: 14:09
 */

namespace Mha\BaOpsStats;
use \Exception;

/**
 * Class Utility
 * @author Michael Hadorn <michael.hadorn@laupercomputing.ch>
 * @package
 * @subpackage
 */
class Utility {

    /**
     * Collection of all tracked processes with starttime
     * @var array
     */
    static protected $trackElements = array();

    /**
     * Tracks a time of a proccess and does direct output of result
     * @param $trackKey
     * @param string $trackText
     * @return int|mixed elapsed time in seconds
     */
    static function trackTime($trackKey, $trackText = '') {
        $time = 0;
        if (!isset(self::$trackElements[$trackKey])) {
            // set start tracking time
            self::$trackElements[$trackKey] = microtime(true);
            if (isset($_GET["tracktime"])) {
                echo 'Starting "' . $trackKey . '" - ' . $trackText . ' (' . self::$trackElements[$trackKey] . ' s)<br>';
            }
        } else {
            $now = microtime(true);
            $time = $now - self::$trackElements[$trackKey];
            self::$trackElements[$trackKey] = $now;
            if (isset($_GET["tracktime"])) {
                echo '<b>Elapsed Time "' . $trackKey . '" in <span alt="' . $time . '" title="' . $time . '">' . round($time, 4) . '</span> seconds </b> - ' . $trackText . ' (' . $now . ' s)<br>';
            }
        }
        return $time;
    }

    /**
     * Builds the timestamp from date as timestamp and time as string
     * @param $date int
     * @param $time string
     * @return int unixtimestamp
     */
    static function buildTimestamp($date, $time) {
        $newDate = 0;
        if (strlen($time) > 0) {
            // time detection (remove letters and force minutes)
            $time = str_replace('h', '', $time);
            if (!strpos($time, '.') && !strpos($time, '::')) {
                $time .= '.00';
            }
            // buld full date for human
            $hDate = date('d.M.Y', $date) . ' ' . $time;
            // build the unix timestamp
            $newDate = strtotime($hDate);
        }

        // fallback: if invalid new date use only the initial value
        if (intval($newDate) == 0) {
            $newDate = $date;
        }

        return $newDate;
    }

    /**
     * @param \DateTime|String $date
     * @return \DateTime
     */
    static function convertDateTime($date) {
        if (!is_object($date) || get_class($date) !== 'DateTime') {
            $date = new \DateTime($date);
        }
        return $date;
    }

    /**
     * Calcs the bmi
     * @param $height in cm
     * @param $weight in kg
     * @return float
     */
    static function bmi($height, $weight) {
        if ($height == 0) {
            return null;
        }
        $height = $height / 100;
        $bmi = $weight / pow($height , 2);
        return $bmi;
    }

}
