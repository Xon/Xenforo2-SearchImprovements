<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\SqlConstraint;
use XFES\Search\Source\Elasticsearch;
use function count;

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