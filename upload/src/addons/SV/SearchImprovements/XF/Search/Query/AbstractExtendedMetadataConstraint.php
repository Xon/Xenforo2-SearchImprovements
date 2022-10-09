<?php

namespace SV\SearchImprovements\XF\Search\Query;

use SV\SearchImprovements\Search\MetadataSearchEnhancements;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\SqlConstraint;
use XFES\Search\Source\Elasticsearch;

/**
 * Class RangeMetadataConstraint
 *
 * @package SV\WordCountSearch\XF\Search\Query
 */
abstract class AbstractExtendedMetadataConstraint extends MetadataConstraint
{
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