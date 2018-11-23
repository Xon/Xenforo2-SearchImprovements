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
     * @param Query\Query $query
     * @param             $maxResults
     * @return array
     * @throws \XF\PrintableException
     */
    public function search(Query\Query $query, $maxResults)
    {
        $query = clone $query;

        return parent::search($query, $maxResults);
    }

    /**
     * @param Query\Query $query
     * @param             $maxResults
     * @return array
     */
    protected function getDslFromQuery(Query\Query $query, $maxResults)
    {
        $dsl = parent::getDslFromQuery($query, $maxResults);
        // rewrite order-by since getDslFromQuery is inflexible
        $orderByClause = $query->getOrder();
        if ($orderByClause instanceof Query\SqlOrder &&
            strpos($orderByClause->getOrder(), 'search_index.word_count') === 0)
        {
            $query->orderedBy('date');
            $dsl['sort'] = [
                ['word_count' => 'desc']
            ];
        }

        return $dsl;
    }

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
