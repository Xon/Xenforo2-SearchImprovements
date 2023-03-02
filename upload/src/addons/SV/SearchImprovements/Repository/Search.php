<?php

namespace SV\SearchImprovements\Repository;

use XF\Mvc\Entity\Repository;
use function array_key_exists;
use function gettype;
use function is_array;
use function is_string;

class Search extends Repository
{
    public function isUsingElasticSearch(): bool
    {
        return \XF::isAddOnActive('XFES') && (\XF::options()->xfesEnabled ?? false);
    }

    public function isPushingViewOtherChecksIntoSearch(): bool
    {
        return (\XF::options()->svPushViewOtherCheckIntoXFES ?? false) && static::isUsingElasticSearch();
    }

    public function addContainerIndexableField(\XF\Mvc\Entity\Structure $structure, string $field): void
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