<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XFES\Search\Source\Elasticsearch;
use function count;

class NotConstraint extends AbstractConstraint
{
    const MATCH_SV_NOT = 'svNOT';

    public function __construct(MetadataConstraint $constraint)
    {
        parent::__construct('', $constraint, self::MATCH_SV_NOT);
    }

    protected function getAllowedMatchTypes(): array
    {
        return [
            self::MATCH_SV_NOT => self::MATCH_SV_NOT,
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
        /** @var MetadataConstraint[] $constraints */
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
            $bool['must_not'] = $childFilters;
        }
        if (count($childNotFilters) !== 0)
        {
            // todo fixme
            $bool['should'] = $childNotFilters;
            $bool['minimum_should_match'] = 1;
        }

        if (count($bool) !== 0)
        {
            $filters[] = [
                'bool' => $bool,
            ];
        }
    }
}