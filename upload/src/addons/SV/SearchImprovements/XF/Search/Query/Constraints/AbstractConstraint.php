<?php

namespace SV\SearchImprovements\XF\Search\Query\Constraints;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\SqlConstraint;
use XFES\Search\Source\Elasticsearch;

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
}