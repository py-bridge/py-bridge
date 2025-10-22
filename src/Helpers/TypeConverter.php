<?php
namespace PyBridge\Helpers;

class TypeConverter
{
    public static function phpToPython($value)
    {
        if (is_array($value)) {
            return array_map([self::class, 'phpToPython'], $value);
        }
        return $value;
    }

    public static function pythonToPhp($value)
    {
        if (is_array($value)) {
            return array_map([self::class, 'pythonToPhp'], $value);
        }
        return $value;
    }
}
