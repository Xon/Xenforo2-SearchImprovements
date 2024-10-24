<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\SqlConstraint;
use XFES\Search\Source\Elasticsearch;
use function array_merge;
use function count;

class AndConstraint extends AbstractConstraint
{
    public const MATCH_AND = -48;
    public const MATCH_SV_AND = 'svAND';

    public function __construct(?MetadataConstraint ...$constraints)
    {
        parent::__construct('', $constraints, self::MATCH_AND);
    }

    protected function getAllowedMatchTypes(): array
    {
        return [
            self::MATCH_AND => self::MATCH_AND,
            self::MATCH_SV_AND => self::MATCH_AND,
        ];
    }

    /**
     * @return SqlConstraint[]
     */
    public function asSqlConstraint(): array
    {
        // TODO: Implement asSqlConstraint() method.
        return [];
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
        if ($childCount === 1)
        {
            $filters = array_merge($filters, $childFilters);
            $childNotFilters = array_merge($filters, $childNotFilters);
            return;
        }

        $bool = [];
        if (count($childFilters) !== 0)
        {
            $bool['filter'] = $childFilters;
        }
        if (count($childNotFilters) !== 0)
        {
            $bool['must_not'] = $childNotFilters;
        }

        if (count($bool) !== 0)
        {
            $filters[] = [
                'bool' => $bool,
            ];
        }
    }
}