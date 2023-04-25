<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XFES\Search\Source\Elasticsearch;
use function count;

/**
 * Forces a query sub-tree to be added to the global 'must not match' list for a search query.
 * Intended for use in getTypePermissionConstraints
 */
class PermissionConstraint extends AbstractConstraint
{
    const MATCH_SV_PERM = 'svPerm';

    public function __construct(?MetadataConstraint $constraint)
    {
        parent::__construct('', $constraint, self::MATCH_SV_PERM);
    }

    protected function getAllowedMatchTypes(): array
    {
        return [
            self::MATCH_SV_PERM => self::MATCH_SV_PERM,
        ];
    }

    public function asSqlConstraint()
    {
        // TODO: Implement asSqlConstraint() method.
        return null;
    }

    /**
     * @param Elasticsearch|MetadataSearchEnhancements $source
     * @param array         $filters
     * @param array         $filtersNot
     */
    public function applyMetadataConstraint(Elasticsearch $source, array &$filters, array &$filtersNot)
    {
        $childFilters = $childNotFilters = [];
        $childCount = $this->processChildConstraints($source, $childFilters, $childNotFilters);
        if ($childCount === 0)
        {
            return;
        }

        if (count($childNotFilters) === 0)
        {
            foreach ($childFilters as $childFilter)
            {
                $filtersNot[] = $childFilter;
            }

            return;
        }

        $bool = [];
        if (count($childFilters) !== 0)
        {
            $bool['filter'] = $childFilters;
        }
        $bool['must_not'] = $childNotFilters;

        $filtersNot[] = [
            'bool' => $bool,
        ];
    }
}