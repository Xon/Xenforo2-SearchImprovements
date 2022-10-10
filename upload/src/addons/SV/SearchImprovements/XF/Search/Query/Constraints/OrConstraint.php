<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XFES\Search\Source\Elasticsearch;
use function count;

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
        $constraints = $this->getValues();

        $childFilters = $childNotFilters = [];
        foreach ($constraints as $constraint)
        {
            if ($constraint === null)
            {
                continue;
            }

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