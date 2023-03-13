<?php

namespace SV\SearchImprovements\Repository;

use SV\SearchImprovements\Globals;
use SV\SearchImprovements\XF\Search\Query\Constraints\RangeConstraint;
use XF\Http\Request;
use XF\Mvc\Entity\Repository;
use XF\Search\Query\Query;
use XF\Search\Query\TableReference;
use function array_key_exists;
use function assert;
use function count;
use function gettype;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function preg_split;

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
     * Query can be KeywordQuery or MoreLikeThisQuery (XFES).
     *
     * @param Query                 $query
     * @param string                $searchField
     * @param int                   $lowerConstraint
     * @param int                   $upperConstraint
     * @param callable(): void      $unsetUpperUrlConstraint
     * @param callable(): void      $unsetLowerUrlConstraint
     * @param array<TableReference> $tableRef
     * @param string|null           $sqlTable
     * @return bool
     */
    public function applyRangeConstraint(\XF\Search\Query\Query $query, string $searchField, int $lowerConstraint, int $upperConstraint, callable $unsetUpperUrlConstraint, callable $unsetLowerUrlConstraint, array $tableRef, ?string $sqlTable = null): bool
    {
        $repo = Globals::repo();
        $source = $repo->isUsingElasticSearch() ? 'search_index' : $sqlTable;
        if ($source === null)
        {
            $source = $tableRef[0]->getAlias();
        }
        if ($lowerConstraint !== 0 && $upperConstraint !== 0)
        {
            $query->withMetadata(new RangeConstraint($searchField, [$upperConstraint, $lowerConstraint], RangeConstraint::MATCH_BETWEEN, $tableRef, $source));
        }
        else if ($lowerConstraint !== 0)
        {
            $unsetUpperUrlConstraint();
            $query->withMetadata(new RangeConstraint($searchField, $lowerConstraint, RangeConstraint::MATCH_GREATER, $tableRef, $source));
        }
        else if ($upperConstraint !== 0)
        {
            $unsetLowerUrlConstraint();
            $query->withMetadata(new RangeConstraint($searchField, $upperConstraint, RangeConstraint::MATCH_LESSER, $tableRef, $source));
        }
        else
        {
            $unsetUpperUrlConstraint();
            $unsetLowerUrlConstraint();

            return false;
        }

        return true;
    }

    /**
     * * Query can be KeywordQuery or MoreLikeThisQuery (XFES).
     *
     * @param Query                  $query
     * @param string                 $searchField
     * @param string                 $constraint
     * @param callable(): void       $unsetUrlConstraint
     * @param callable(string): void|null $updateUrlConstraint
     * @return bool
     */
    public function applyUserConstraint(\XF\Search\Query\Query $query, string $searchField, string $constraint, callable $unsetUrlConstraint, ?callable $updateUrlConstraint): bool
    {
        if ($constraint !== '')
        {
            $unsetUrlConstraint();

            return false;
        }

        $users = preg_split('/,\s*/', $constraint, -1, PREG_SPLIT_NO_EMPTY);
        if (count($users) === 0)
        {
            $unsetUrlConstraint();

            return false;
        }

        /** @var \XF\Repository\User $userRepo */
        $userRepo = \XF::repository('XF:User');
        $matchedUsers = $userRepo->getUsersByNames($users, $notFound);
        if (count($notFound) !== 0)
        {
            $query->error('users', \XF::phrase('following_members_not_found_x', ['members' => \implode(', ', $notFound)]));

            return false;
        }

        $query->withMetadata($searchField, $matchedUsers->keys());
        if ($updateUrlConstraint !== null)
        {
            $updateUrlConstraint(implode(', ', $users));
        }

        return true;
    }
}