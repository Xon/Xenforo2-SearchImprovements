<?php

namespace SV\SearchImprovements\Repository;

use SV\SearchImprovements\Repository\Search as SearchRepo;
use SV\SearchImprovements\Search\SearchSourceExtractor;
use SV\SearchImprovements\Util\Arr;
use SV\SearchImprovements\XF\Search\Query\Constraints\DateRangeConstraint;
use SV\SearchImprovements\XF\Search\Query\Constraints\RangeConstraint;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
use XF\Search\Query\Query;
use XF\Search\Query\TableReference;
use XFES\Search\Source\Elasticsearch as ElasticsearchSource;
use function array_filter;
use function array_key_exists;
use function assert;
use function count;
use function gettype;
use function implode;
use function is_array;
use function is_string;
use function preg_split;

class Search extends Repository
{
    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function isShowingTagTooltipForType(string $contentType): bool
    {
        return true;
    }

    /**
     * @param array<?string> $types
     * @return bool
     * @noinspection PhpRedundantOptionalArgumentInspection
     */
    public function isShowingTagTooltip(array $types): bool
    {
        /** @var array<string> $types */
        $types = array_filter($types, function (?string $s) {
            return $s !== null && $s !== '';
        });
        if (count($types) === 0)
        {
            // general search, likely will hit tags
            return true;
        }

        $tagRepo = Helper::repository(\XF\Repository\Tag::class);
        assert($tagRepo instanceof \XF\Repository\Tag);

        foreach ($types as $type)
        {
            $tagHandler = $tagRepo->getTagHandler($type, false);
            if ($tagHandler !== null && $this->isShowingTagTooltipForType($type))
            {
                return true;
            }
        }

        return false;
    }

    public function isUsingElasticSearch(): bool
    {
        return Helper::isAddOnActive('XFES') && (\XF::options()->xfesEnabled ?? false);
    }

    public function isPushingViewOtherChecksIntoSearch(): bool
    {
        return (\XF::options()->svPushViewOtherCheckIntoXFES ?? false) && $this->isUsingElasticSearch();
    }

    public function addContainerIndexableField(\XF\Mvc\Entity\Structure $structure, string $field): void
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

    /**
     * Query can be KeywordQuery or MoreLikeThisQuery (XFES).
     *
     * @param Query                 $query
     * @param array                 $constraints
     * @param array                 $urlConstraints
     * @param string                $lowerConstraintField
     * @param string                $upperConstraintField
     * @param string                $searchField
     * @param array<TableReference> $tableRef
     * @param string|null           $sqlTable
     * @return bool
     */
    public function applyRangeConstraint(Query $query, array $constraints, array &$urlConstraints, string $lowerConstraintField, string $upperConstraintField, string $searchField, array $tableRef, ?string $sqlTable = null): bool
    {
        $lowerConstraint = (int)Arr::getByPath($constraints, $lowerConstraintField);
        $upperConstraint = Arr::getByPath($constraints, $upperConstraintField);
        if ($upperConstraint !== null)
        {
            $upperConstraint = (int)$upperConstraint;
        }
        if ($upperConstraint === 0)
        {
            $lowerConstraint = 0;
        }

        $repo = SearchRepo::get();
        $source = $repo->isUsingElasticSearch() ? 'search_index' : $sqlTable;
        if ($source === null)
        {
            $source = $tableRef[0]->getAlias();
        }
        if ($lowerConstraint !== 0 && $upperConstraint !== null)
        {
            $query->withMetadata(new RangeConstraint($searchField, [$upperConstraint, $lowerConstraint], RangeConstraint::MATCH_BETWEEN, $tableRef, $source));
        }
        else if ($lowerConstraint !== 0)
        {
            Arr::unsetUrlConstraint($urlConstraints, $upperConstraintField);
            $query->withMetadata(new RangeConstraint($searchField, $lowerConstraint, RangeConstraint::MATCH_GREATER, $tableRef, $source));
        }
        else if ($upperConstraint !== null)
        {
            Arr::unsetUrlConstraint($urlConstraints, $lowerConstraintField);
            $query->withMetadata(new RangeConstraint($searchField, $upperConstraint, RangeConstraint::MATCH_LESSER, $tableRef, $source));
        }
        else
        {
            Arr::unsetUrlConstraint($urlConstraints, $lowerConstraintField);
            Arr::unsetUrlConstraint($urlConstraints, $upperConstraintField);

            return false;
        }

        return true;
    }

    public function getEntityToMessage(Entity $entity): string
    {
        $message = '';
        foreach ($entity->structure()->columns as $column => $schema)
        {
            if (
                ($schema['type'] ?? '') !== Entity::STR ||
                !empty($schema['allowedValues']) || // aka enums
                ($schema['noIndex'] ?? false)
            )
            {
                continue;
            }

            $value = $entity->get($column);
            if ($value === null || $value === '')
            {
                continue;
            }

            $message .= "\n" . $value;
        }

        return $message;
    }

    /**
     * ElasticSearch can be configured to transform dates in various ways.
     * This is a hook so php can generate correct queries
     *
     * @param int $date
     * @return int|mixed
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    protected function transformDate(int $date)
    {
        return $date;
    }

    /**
     * Query can be KeywordQuery or MoreLikeThisQuery (XFES).
     *
     * @param Query                 $query
     * @param array                 $constraints
     * @param array                 $urlConstraints
     * @param string                $lowerConstraintField
     * @param string                $upperConstraintField
     * @param string                $searchField
     * @param array<TableReference> $tableRef
     * @param string|null           $sqlTable
     * @param int                   $neverHandling
     * @return bool
     */
    public function applyDateRangeConstraint(Query $query, array $constraints, array &$urlConstraints, string $lowerConstraintField, string $upperConstraintField, string $searchField, array $tableRef, ?string $sqlTable = null, int $neverHandling = DateRangeConstraint::NEVER_IS_ZERO): bool
    {
        $lowerConstraint = (int)Arr::getByPath($constraints, $lowerConstraintField);
        $upperConstraint = Arr::getByPath($constraints, $upperConstraintField);
        if ($upperConstraint !== null)
        {
            $upperConstraint = (int)$upperConstraint;
        }
        if ($upperConstraint === 0)
        {
            $lowerConstraint = 0;
        }

        $repo = SearchRepo::get();
        $source = $repo->isUsingElasticSearch() ? 'search_index' : $sqlTable;
        if ($source === null)
        {
            $source = $tableRef[0]->getAlias();
        }
        if ($lowerConstraint !== 0 && $upperConstraint !== null)
        {
            $query->withMetadata(new DateRangeConstraint($searchField, [$this->transformDate($upperConstraint), $this->transformDate($lowerConstraint)], RangeConstraint::MATCH_BETWEEN, $neverHandling, $tableRef, $source));
        }
        else if ($lowerConstraint !== 0)
        {
            Arr::unsetUrlConstraint($urlConstraints, $upperConstraintField);
            $query->withMetadata(new DateRangeConstraint($searchField, $this->transformDate($lowerConstraint), RangeConstraint::MATCH_GREATER, $neverHandling, $tableRef, $source));
        }
        else if ($upperConstraint !== null)
        {
            Arr::unsetUrlConstraint($urlConstraints, $lowerConstraintField);
            $query->withMetadata(new DateRangeConstraint($searchField, $this->transformDate($upperConstraint), RangeConstraint::MATCH_LESSER, $neverHandling, $tableRef, $source));
        }
        else
        {
            Arr::unsetUrlConstraint($urlConstraints, $lowerConstraintField);
            Arr::unsetUrlConstraint($urlConstraints, $upperConstraintField);

            return false;
        }

        return true;
    }

    /**
     * * Query can be KeywordQuery or MoreLikeThisQuery (XFES).
     *
     * @param Query  $query
     * @param array  $constraints
     * @param array  $urlConstraints
     * @param string $constraintField
     * @param string $searchField
     * @return bool
     */
    public function applyUserConstraint(Query $query, array $constraints, array &$urlConstraints, string $constraintField, string $searchField): bool
    {
        $constraint = (string)Arr::getByPath($constraints, $constraintField);
        if ($constraint === '')
        {
            Arr::unsetUrlConstraint($urlConstraints, $constraintField);

            return false;
        }

        $users = preg_split('/,\s*/', $constraint, -1, PREG_SPLIT_NO_EMPTY);
        if (count($users) === 0)
        {
            Arr::unsetUrlConstraint($urlConstraints, $constraintField);

            return false;
        }

        $userRepo = Helper::repository(\XF\Repository\User::class);
        $matchedUsers = $userRepo->getUsersByNames($users, $notFound);
        if (count($notFound) !== 0)
        {
            $query->error('users', \XF::phrase('following_members_not_found_x', ['members' => implode(', ', $notFound)]));

            return false;
        }

        $query->withMetadata($searchField, $matchedUsers->keys());
        Arr::setUrlConstraint($urlConstraints, $constraintField, implode(', ', $users));

        return true;
    }

    /**
     * @param string $contentType
     * @param string|int $id
     * @return string
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function getSearchIdFromEntityId(string $contentType, $id): string
    {
        $specializedSearchIndexRepo = SpecializedSearchIndex::get();
        $types = $specializedSearchIndexRepo->getSearchHandlerDefinitions();
        if (isset($types[$contentType]))
        {
            $search = $specializedSearchIndexRepo->search($contentType);
        }
        else
        {
            $search = \XF::app()->search();
        }

        $source = SearchSourceExtractor::getSource($search);
        assert($source instanceof ElasticsearchSource);

        /** @noinspection PhpDeprecationInspection */
        if ($source->getEsApi()->isSingleTypeIndex())
        {
            return $contentType . '-' . $id;
        }

        return $id;
    }
}