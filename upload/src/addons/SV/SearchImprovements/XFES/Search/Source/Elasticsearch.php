<?php

namespace SV\SearchImprovements\XFES\Search\Source;

use SV\SearchImprovements\XF\Search\Query\RangeMetadataConstraint;
use XF\Search\Query;

/**
 * Class Elasticsearch
 *
 * @package SV\WordCountSearch\XFES\Search\Source
 */
class Elasticsearch extends XFCP_Elasticsearch
{
    /**
     * @param Query\MetadataConstraint $metadata
     * @param array                    $filters
     * @param array                    $filtersNot
     */
    protected function applyMetadataConstraint(Query\MetadataConstraint $metadata, array &$filters, array &$filtersNot)
    {
        if ($metadata instanceof RangeMetadataConstraint)
        {
            $values = $metadata->getValues();

            switch ($metadata->getMatchType())
            {
                case RangeMetadataConstraint::MATCH_LESSER:
                    $filters[] = [
                        'range' => [
                            $metadata->getKey() => [
                                "lte" => $values[0],
                            ]
                        ]
                    ];

                    return;
                case RangeMetadataConstraint::MATCH_GREATER:
                    $filters[] = [
                        'range' => [
                            $metadata->getKey() => [
                                "gte" => $values[0],
                            ]
                        ]
                    ];

                    return;
                case RangeMetadataConstraint::MATCH_BETWEEN:
                    $filters[] = [
                        'range' => [
                            $metadata->getKey() => [
                                "lte" => $values[0],
                                "gte" => $values[1],
                            ]
                        ]
                    ];

                    return;
            }
        }
        parent::applyMetadataConstraint($metadata, $filters, $filtersNot);
    }
}
