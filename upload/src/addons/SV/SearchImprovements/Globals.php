<?php


namespace SV\SearchImprovements;

use function array_key_exists;
use function gettype;

/**
 * This class is used to encapsulate global state between layers without using $GLOBAL[] or relying on the consumer
 * being loaded correctly by the dynamic class autoloader
 * Class Globals
 *
 * @package SV\SearchImprovements
 */
class Globals
{
    public static $shimSearchForSpecialization = true;

    public static function isUsingElasticSearch(): bool
    {
        return \XF::isAddOnActive('XFES') && (\XF::options()->xfesEnabled ?? false);
    }

    public static function isPushingViewOtherChecksIntoSearch(): bool
    {
        return (\XF::options()->svPushViewOtherCheckIntoXFES ?? false) && static::isUsingElasticSearch();
    }

    private function __construct() { }

    public static function addContainerIndexableField(\XF\Mvc\Entity\Structure $structure, string $field): void
    {
        $container = $structure->behaviors['XF:IndexableContainer']?? null;
        if ($container === null)
        {
            return;
        }

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
            \XF::logException(new \LogicException('Unexpected type ('.gettype($container['checkForUpdates']).') for XF:IndexableContainer option checkForUpdates '));
            return;
        }

        $container['checkForUpdates'][] = $field;
    }
}
