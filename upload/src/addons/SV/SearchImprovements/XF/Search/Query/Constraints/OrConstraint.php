<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XFES\Search\Source\Elasticsearch;
use function array_filter;
use function array_merge;
use function count;
use function reset;

class OrConstraint extends AbstractConstraint
{
    const MATCH_SV_OR = 'svOR';

    public function __construct(?MetadataConstraint ...$constraints)
    {
        parent::__construct('', $constraints, self::MATCH_SV_OR);
    }

    protected function getAllowedMatchTypes(): array
    {
        return [
            self::MATCH_SV_OR => self::MATCH_SV_OR,
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
        /** @var array<MetadataConstraint|null> $constraints */
        $constraints = array_filter($this->getValues(), function ($v): bool {
            return $v !== null;
        });
        if (count($constraints) === 0)
        {
            return;
        }
        else if (count($constraints) === 1)
        {
            $constraint = reset($constraints);
            $source->svApplyMetadataConstraint($constraint, $filters, $filtersNot);

            return;
        }

        $childFilters = $childNotFilters = [];
        foreach ($constraints as $constraint)
        {
            $source->svApplyMetadataConstraint($constraint, $childFilters, $childNotFilters);
        }

        $bool = [];
        if (count($childFilters) !== 0)
        {
            $bool['should'] = $childFilters;
            $bool['minimum_should_match'] = 1;
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