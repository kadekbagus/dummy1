<?php namespace Orbit;

class Text
{

    /**
     * Extracted Global date format
     * @param string $dateString
     * @param string $format
     * @return bool|string
     */
    public static function formatDateTime($dateString, $format = 'd M Y H:i:s')
    {
        return date($format, strtotime($dateString));
    }

    /**
     * @param string $dateString
     * @param string $format
     * @return bool|string
     */
    public static function formatTime($dateString, $format = 'H:i:s')
    {
        return static::formatDateTime($dateString, $format);
    }

    /**
     * @param string $dateString
     * @param string $format
     * @return bool|string
     */
    public static function formatDate($dateString, $format = 'd M Y')
    {
        return static::formatDateTime($dateString, $format);
    }
    /**
     * @param string|int $number
     * @param int $precision
     * @return string
     */
    public static function formatNumber($number, $precision = 2)
    {
        return number_format($number, $precision);
    }
}
