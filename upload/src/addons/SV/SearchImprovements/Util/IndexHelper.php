<?php

namespace SV\SearchImprovements\Util;

use XF\Mvc\Entity\Structure;
use function array_key_exists;
use function floatval;
use function gettype;
use function is_array;
use function is_string;
use function strval;

abstract class IndexHelper
{
    /**
     * Con
     * @param int|float|string $value
     * @return int|float
     */
    public static function asIntOrFloat($value)
    {
        /** @noinspection PhpWrongStringConcatenationInspection */
        return strval(floatval($value)) + 0;
    }

    /**
     * @since 2.18.0
     */
    public static function isUsingElasticSearch(): bool
    {
        if (!(\XF::options()->xfesEnabled ?? false))
        {
            return false;
        }

        $addOns = \XF::app()->container('addon.cache');

        /** @noinspection SpellCheckingInspection */
        return isset($addOns['XFES']);
    }

    /**
     * @since 2.18.0
     */
    public static function addContainerIndexableField(Structure $structure, string $field): void
    {
        if (!array_key_exists('XF:IndexableContainer', $structure->behaviors))
        {
            return;
        }
        $container =& $structure->behaviors['XF:IndexableContainer'];

        if (!array_key_exists('checkForUpdates', $container))
        {
            $container['checkForUpdates'] = [];
        }
        else if (is_string($container['checkForUpdates']))
        {
            $container['checkForUpdates'] = [$container['checkForUpdates']];
        }
        else if (!is_array($container['checkForUpdates']))
        {
            \XF::logException(new \LogicException('Unexpected type (' . gettype($container['checkForUpdates']) . ') for XF:IndexableContainer option checkForUpdates '));

            return;
        }

        $container['checkForUpdates'][] = $field;
    }
}