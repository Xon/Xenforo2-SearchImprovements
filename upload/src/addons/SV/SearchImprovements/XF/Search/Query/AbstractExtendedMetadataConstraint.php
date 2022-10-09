<?php

namespace SV\SearchImprovements\XF\Search\Query;

use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\SqlConstraint;
use XF\Search\Query\TableReference;

/**
 * Class RangeMetadataConstraint
 *
 * @package SV\WordCountSearch\XF\Search\Query
 */
abstract class AbstractExtendedMetadataConstraint extends MetadataConstraint
{
    /**
     * @return null|SqlConstraint
     */
    abstract public function asSqlConstraint();

    /**
     * @param array              $filters
     * @param array              $filtersNot
     */
    abstract public function applyMetadataConstraint(array &$filters, array &$filtersNot);
}