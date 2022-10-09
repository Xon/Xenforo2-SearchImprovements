<?php

namespace SV\SearchImprovements\Search;

use SV\SearchImprovements\XF\Search\Query\AbstractExtendedMetadataConstraint;
use XF\Search\Query\MetadataConstraint;

trait MetadataSearchEnhancements
{
    /**
     * @param MetadataConstraint $metadata
     * @param array              $filters
     * @param array              $filtersNot
     */
    protected function applyMetadataConstraint(MetadataConstraint $metadata, array &$filters, array &$filtersNot)
    {
        if ($metadata instanceof AbstractExtendedMetadataConstraint)
        {
            $metadata->applyMetadataConstraint($this, $filters, $filtersNot);

            return;
        }

        /** @noinspection PhpMultipleClassDeclarationsInspection */
        parent::applyMetadataConstraint($metadata, $filters, $filtersNot);
    }

    public function svApplyMetadataConstraint(MetadataConstraint $metadata, array &$filters, array &$filtersNot)
    {
        $this->applyMetadataConstraint($metadata, $filters, $filtersNot);
    }
}