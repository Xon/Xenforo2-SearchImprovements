<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 */

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Mvc\Entity\AbstractCollection;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\SqlConstraint;
use XFES\Search\Source\Elasticsearch;
use function array_filter;
use function count;
use function is_array;
use function reset;

abstract class AbstractConstraint extends MetadataConstraint
{
    /**
     * @param string $key
     * @param mixed  $values
     * @param string $matchType
     */
    public function __construct(string $key, $values, string $matchType)
    {
        parent::__construct($key, $values, $matchType);
    }

    public function setValues($values)
    {
        if ($values instanceof AbstractCollection)
        {
            $values = $values->toArray();
        }

        if (!is_array($values))
        {
            $values = [$values];
        }

        $values = array_filter($values, function ($v): bool {
            return $v !== null;
        });

        $this->values = $values;
    }

    /**
     * @return array<int|string, int>
     */
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
        /** @var array<MetadataConstraint> $constraints */
        $constraints = $this->getValues();
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