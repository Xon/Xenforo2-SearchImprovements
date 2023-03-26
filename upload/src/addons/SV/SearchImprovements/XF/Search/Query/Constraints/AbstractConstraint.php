<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\SqlConstraint;
use XFES\Search\Source\Elasticsearch;
use function array_filter;
use function count;
use function reset;

abstract class AbstractConstraint extends MetadataConstraint
{
    abstract protected function getAllowedMatchTypes(): array;

    /**
     * @param string|int $match
     * @return void
     */
    public function setMatchType($match)
    {
        $allowedTypes = $this->getAllowedMatchTypes();
        $mappedType = $allowedTypes[$match] ?? null;
        if ($mappedType !== null)
        {
            $this->matchType = $mappedType;
            return;
        }

        parent::setMatchType($match);
    }

    /**
     * @return null|SqlConstraint|SqlConstraint[]
     */
    abstract public function asSqlConstraint();

    /**
     * @param Elasticsearch|MetadataSearchEnhancements $source
     * @param array         $filters
     * @param array         $filtersNot
     */
    abstract public function applyMetadataConstraint(Elasticsearch $source, array &$filters, array &$filtersNot);

    /**
     * @param Elasticsearch|MetadataSearchEnhancements $source
     * @param array                                    $childFilters
     * @param array                                    $childNotFilters
     * @return int
     */
    protected function processChildConstraints(Elasticsearch $source, array &$childFilters, array &$childNotFilters): int
    {
        /** @var array<MetadataConstraint|null> $constraints */
        $constraints = array_filter($this->getValues(), function ($v): bool {
            return $v !== null;
        });
        if (count($constraints) === 0)
        {
            return 0;
        }
        else if (count($constraints) === 1)
        {
            $constraint = reset($constraints);
            $source->svApplyMetadataConstraint($constraint, $childFilters, $childNotFilters);
        }

        $childFilters = $childNotFilters = [];
        foreach ($constraints as $constraint)
        {
            $source->svApplyMetadataConstraint($constraint, $childFilters, $childNotFilters);
        }

        return count($constraints);
    }
}