<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\SqlConstraint;
use XFES\Search\Source\Elasticsearch;
use function array_filter;
use function count;
use function reset;

class AndConstraint extends AbstractConstraint
{
    const MATCH_AND = 'svAND';

    public function __construct(?MetadataConstraint ...$constraints)
    {
        parent::__construct('', $constraints, self::MATCH_AND);
    }

    protected function getAllowedMatchTypes(): array
    {
        return [
            self::MATCH_AND => self::MATCH_AND,
        ];
    }

    /**
     * @return null|SqlConstraint
     */
    public function asSqlConstraint(): ?SqlConstraint
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