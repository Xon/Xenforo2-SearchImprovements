<?php

namespace SV\SearchImprovements\Repository;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\XF\Search\Query\Constraints\RangeConstraint;
use XF\Http\Request;
use XF\Mvc\Entity\Repository;
use XF\Search\Query\Query;
use XF\Search\Query\TableReference;
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
        $container = $structure->behaviors['XF:IndexableContainer'] ?? null;
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
            \XF::logException(new \LogicException('Unexpected type (' . gettype($container['checkForUpdates']) . ') for XF:IndexableContainer option checkForUpdates '));

            return;
        }

        $container['checkForUpdates'][] = $field;
    }

    /**
     * @param Query                 $query
     * @param Request               $request
     * @param callable              $unsetUpper
     * @param callable              $unsetLower
     * @param array<TableReference> $tableRef
     * @param string|null           $sqlTable
     * @return bool
     */
    public function applyRepliesConstraint(\XF\Search\Query\Query $query, \XF\Http\Request $request, callable $unsetUpper, callable $unsetLower, array $tableRef, ?string $sqlTable = null): bool
    {
        return $this->applyRangeConstraint($query, $request, 'replies', 'c.replies.lower', 'c.replies.upper', $unsetUpper, $unsetLower, $tableRef, $sqlTable);
    }

    /**
     * @param \XF\Search\Query\Query $query
     * @param \XF\Http\Request       $request
     * @param string                 $field
     * @param string                 $lowerConstraint
     * @param string                 $upperConstraint
     * @param callable               $unsetUpper
     * @param callable               $unsetLower
     * @param array<TableReference>  $tableRef
     * @param string|null            $sqlTable
     * @return bool
     */
    public function applyRangeConstraint(\XF\Search\Query\Query $query, \XF\Http\Request $request, string $field, string $lowerConstraint, string $upperConstraint, callable $unsetUpper, callable $unsetLower, array $tableRef, ?string $sqlTable = null): bool
    {
        $lowerConstraint = $request->filter($lowerConstraint, 'uint');
        $upperConstraint = $request->filter($upperConstraint, 'uint');

        $repo = Globals::repo();
        $source = $repo->isUsingElasticSearch() ? 'search_index' : $sqlTable;
        if ($source === null)
        {
            $source = $tableRef[0]->getAlias();
        }
        if ($lowerConstraint !== 0 && $upperConstraint !== 0)
        {
            $query->withMetadata(new RangeConstraint($field, [$upperConstraint, $lowerConstraint], RangeConstraint::MATCH_BETWEEN, $tableRef, $source));
        }
        else if ($lowerConstraint !== 0)
        {
            $unsetUpper();
            $query->withMetadata(new RangeConstraint($field, $lowerConstraint, RangeConstraint::MATCH_GREATER, $tableRef, $source));
        }
        else if ($upperConstraint !== 0)
        {
            $unsetLower();
            $query->withMetadata(new RangeConstraint($field, $upperConstraint, RangeConstraint::MATCH_LESSER, $tableRef, $source));
        }
        else
        {
            $unsetUpper();
            $unsetLower();

            return false;
        }

        return true;
    }
}