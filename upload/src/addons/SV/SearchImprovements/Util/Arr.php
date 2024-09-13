<?php

namespace SV\SearchImprovements\Util;

use function array_key_exists;
use function array_shift;
use function assert;
use function count;
use function explode;
use function is_array;
use function preg_replace;

/**
 * For working with XF filter result arrays which can be dotted keys or arrays, or both
 */
abstract class Arr
{
    private function __construct() { }

    public static function existsByPath(array $array, string $key): bool
    {
        $subParts = explode('.', $key);
        if (array_key_exists($key, $array))
        {
            return true;
        }

        $part = $array;
        do
        {
            if (!is_array($part))
            {
                return false;
            }

            $key = array_shift($subParts);
            if (!array_key_exists($key, $part))
            {
                return false;
            }
            if (count($subParts) === 0)
            {
                return true;
            }
            $part = $part[$key];
        }
        while (true);
    }

    /**
     * @param array  $array
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function getByPath(array $array, string $key, $default = null)
    {
        $subParts = explode('.', $key);
        if (array_key_exists($key, $array))
        {
            return $array[$key];
        }

        $part = $array;
        do
        {
            if (!is_array($part))
            {
                return $default;
            }

            $key = array_shift($subParts);
            if (!array_key_exists($key, $part))
            {
                return $default;
            }
            $part = $part[$key];
            if (count($subParts) === 0)
            {
                return $part;
            }
        }
        while (true);
    }

    /**
     * @param array  $array
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public static function setByPath(array &$array, string $key, $value): bool
    {
        $subParts = explode('.', $key);
        if (array_key_exists($key, $array))
        {
            $array[$key] = $value;
            return true;
        }

        $part = &$array;
        do
        {
            if (!is_array($part))
            {
                return false;
            }

            $key = array_shift($subParts);
            if (!array_key_exists($key, $part))
            {
                return false;
            }
            $part = &$part[$key];
            if (count($subParts) === 0)
            {
                $part = $value;
                return true;
            }
        }
        while (true);
    }

    /**
     * @param array  $array
     * @param string $key
     * @return void
     */
    public static function unsetByPath(array &$array, string $key): bool
    {
        $subParts = explode('.', $key);
        if (array_key_exists($key, $array))
        {
            unset($array[$key]);
            return true;
        }

        $part = &$array;
        do
        {
            if (!is_array($part))
            {
                return false;
            }

            $key = array_shift($subParts);
            if (!array_key_exists($key, $part))
            {
                return false;
            }
            if (count($subParts) === 0)
            {
                unset($part[$key]);
                return true;
            }
            $part = &$part[$key];
        }
        while (true);
    }

    /**
     * XF search constraints are prefixed with 'c.' but the url search constraint is not
     *
     * @param array  $array
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public static function setUrlConstraint(array &$array, string $key, $value): bool
    {
        $key = preg_replace('/^c\./','', $key);

        return static::setByPath($array, $key, $value);
    }

    /**
     * XF search constraints are prefixed with 'c.' but the url search constraint is not
     *
     * @param array  $array
     * @param string $key
     * @return bool
     */
    public static function unsetUrlConstraint(array &$array, string $key): bool
    {
        $key = preg_replace('/^c\./','', $key);

        return static::unsetByPath($array, $key);
    }
}