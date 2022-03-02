<?php
namespace Tk\Table\Cell;


/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class Minutes extends Text
{

    public function getPropertyValue($obj)
    {
        $value = self::min2Str($this->getObjectPropertyValue($obj));
        return $value;
    }

    /**
     * Convert minutes to a `h:m` string
     *
     * @param int $minutes
     * @param int $dayHours (optional) If supplied then days are added with the number of $dayHours, generally 8hours in a working day not 24
     * @return string
     */
    public static function min2Str($minutes, $dayHours = 0)
    {
        $h = floor($minutes / 60);
        $m = $minutes - ($h * 60);
        if (!$dayHours)
            return sprintf('%02d:%02d', $h, $m);

        $d = floor($minutes / (60*$dayHours));
        $h = floor(($minutes-($d*(60*$dayHours))) / 60);
        $m = $minutes - ($h * 60) - ($d*(60*$dayHours));
        return sprintf('%02d:%02d:%02d', $d, $h, $m);
    }

    /**
     * Convert a string in the format of `h:m` to minutes
     *
     * @param $str
     * @return int
     */
    public static function str2min($str)
    {
        list($h, $m) = explode(':', $str);
        return (int)(((int)$h*60)+(int)$m);
    }

}